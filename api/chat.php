<?php

ini_set('display_errors', '0');

session_start();

require_once __DIR__ . '/../db/init.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Usuário';
$pdo      = getDB();
$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? $_POST['action'] ?? '';

// ─── GET: history ────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'history') {
    header('Content-Type: application/json; charset=utf-8');

    $charId = (int)($_GET['character_id'] ?? 0);
    if (!$charId) {
        http_response_code(400);
        echo json_encode(['error' => 'character_id é obrigatório.']);
        exit;
    }

    // Verify ownership
    $char = $pdo->prepare("SELECT * FROM characters WHERE id=? AND user_id=?");
    $char->execute([$charId, $userId]);
    if (!$char->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Personagem não encontrado.']);
        exit;
    }

    // Mark as read
    $pdo->prepare("UPDATE messages SET read_at=CURRENT_TIMESTAMP WHERE character_id=? AND user_id=? AND role='assistant' AND read_at IS NULL")
        ->execute([$charId, $userId]);

    $limit = 100;
    $stmt  = $pdo->prepare("SELECT * FROM messages WHERE character_id=? AND user_id=? ORDER BY created_at ASC LIMIT ?");
    $stmt->execute([$charId, $userId, $limit]);
    $messages = $stmt->fetchAll();

    echo json_encode(['messages' => $messages]);
    exit;
}

// ─── POST: clear ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'clear') {
    header('Content-Type: application/json; charset=utf-8');

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }

    $charId = (int)($_POST['character_id'] ?? 0);
    if (!$charId) {
        http_response_code(400);
        echo json_encode(['error' => 'character_id é obrigatório.']);
        exit;
    }

    $pdo->prepare("DELETE FROM messages WHERE character_id=? AND user_id=?")->execute([$charId, $userId]);
    echo json_encode(['success' => true]);
    exit;
}

// ─── SSE Stream ──────────────────────────────────────────────────────────────
if (isset($_GET['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    $charId  = (int)($_GET['character_id'] ?? 0);
    $message = trim($_GET['message'] ?? '');
    $fileUrl  = trim($_GET['file_url'] ?? '');
    $fileName = trim($_GET['file_name'] ?? '');
    $fileType = trim($_GET['file_type'] ?? '');

    if (!$charId || !$message) {
        echo "data: " . json_encode(['error' => 'Parâmetros inválidos.']) . "\n\n";
        flush();
        exit;
    }

    // Rate limiting
    $sessionId = session_id();
    $rateRow   = $pdo->prepare("SELECT * FROM rate_limits WHERE session_id=?");
    $rateRow->execute([$sessionId]);
    $rate = $rateRow->fetch();

    if ($rate) {
        $windowStart = strtotime($rate['window_start']);
        if (time() - $windowStart < 60) {
            if ($rate['requests'] >= 30) {
                echo "data: " . json_encode(['error' => 'Limite de requisições atingido. Aguarde.']) . "\n\n";
                flush();
                exit;
            }
            $pdo->prepare("UPDATE rate_limits SET requests=requests+1 WHERE session_id=?")
                ->execute([$sessionId]);
        } else {
            $pdo->prepare("UPDATE rate_limits SET requests=1, window_start=CURRENT_TIMESTAMP WHERE session_id=?")
                ->execute([$sessionId]);
        }
    } else {
        $pdo->prepare("INSERT INTO rate_limits (session_id, requests) VALUES (?,1)")->execute([$sessionId]);
    }

    // Load character
    $charStmt = $pdo->prepare("SELECT * FROM characters WHERE id=? AND user_id=?");
    $charStmt->execute([$charId, $userId]);
    $character = $charStmt->fetch();
    if (!$character) {
        echo "data: " . json_encode(['error' => 'Personagem não encontrado.']) . "\n\n";
        flush();
        exit;
    }

    // Load ai_config
    $config = $pdo->query("SELECT * FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
    if (!$config) {
        echo "data: " . json_encode(['error' => 'IA não configurada.']) . "\n\n";
        flush();
        exit;
    }

    // Context messages
    $ctxLimit = max(1, (int)$character['context_messages']);
    $histStmt = $pdo->prepare("
        SELECT role, content FROM messages
        WHERE character_id=? AND user_id=?
        ORDER BY created_at DESC LIMIT ?
    ");
    $histStmt->execute([$charId, $userId, $ctxLimit]);
    $historyRaw  = array_reverse($histStmt->fetchAll());
    $historyMsgs = array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content']], $historyRaw);

    // Save user message
    $pdo->prepare("
        INSERT INTO messages (user_id, character_id, role, content, file_url, file_name, file_type)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$userId, $charId, 'user', $message, $fileUrl ?: null, $fileName ?: null, $fileType ?: null]);

    // Build system prompt
    $systemPrompt = buildSystemPrompt($character, $userName);

    // Determine model
    $model = determineModel($config);

    // Append file content to message if applicable
    $userMessageContent = $message;
    if ($fileUrl && $character['can_read_files']) {
        $basePath = realpath(__DIR__ . '/../');
        $uploadsFilesPath = $basePath ? realpath($basePath . '/uploads/files') : false;
        $candidatePath = $basePath ? realpath($basePath . '/' . ltrim($fileUrl, '/')) : false;

        $isPathSafe = $candidatePath !== false
            && $uploadsFilesPath !== false
            && strpos($candidatePath, $uploadsFilesPath . DIRECTORY_SEPARATOR) === 0
            && is_file($candidatePath);

        if ($isPathSafe) {
            $fileMime = mime_content_type($candidatePath);
            $contextContent = '';

            if (strpos($fileMime, 'image/') === 0) {
                $raw = file_get_contents($candidatePath);
                if ($raw !== false) {
                    $contextContent = '[imagem base64 omitida para contexto] ' . substr(base64_encode($raw), 0, 4000);
                }
            } elseif ($fileMime === 'application/pdf') {
                if (function_exists('shell_exec')) {
                    $escaped = escapeshellarg($candidatePath);
                    $pdfText = shell_exec("pdftotext {$escaped} - 2>/dev/null");
                    if (is_string($pdfText) && trim($pdfText) !== '') {
                        $contextContent = trim($pdfText);
                    }
                }
                if ($contextContent === '') {
                    $contextContent = '[PDF enviado, sem extração de texto disponível no servidor]';
                }
            } elseif (strpos($fileMime, 'text/') === 0 || in_array($fileMime, ['application/json', 'application/javascript'], true)) {
                $raw = file_get_contents($candidatePath);
                if ($raw !== false) {
                    $contextContent = $raw;
                }
            }

            if ($contextContent !== '') {
                $userMessageContent .= "\n\nO usuário enviou um arquivo chamado {$fileName} com o seguinte conteúdo: " . substr($contextContent, 0, 4000);
            }
        }
    }

    $fullResponse = '';

    try {
        $fullResponse = streamAI($config, $model, $systemPrompt, $historyMsgs, $userMessageContent, $character);
    } catch (Exception $e) {
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }

    // Save assistant message
    if ($fullResponse) {
        $pdo->prepare("
            INSERT INTO messages (user_id, character_id, role, content, read_at)
            VALUES (?,?,'assistant',?,CURRENT_TIMESTAMP)
        ")->execute([$userId, $charId, $fullResponse]);
    }

    echo "data: [DONE]\n\n";
    flush();
    exit;
}

// ─── POST: send (non-streaming fallback) ─────────────────────────────────────
if ($method === 'POST' && $action === 'send') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Use stream endpoint.']);
    exit;
}

// ─── SSE: Group stream ────────────────────────────────────────────────────────
if (isset($_GET['group_stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    $groupId = (int)($_GET['group_id'] ?? 0);
    $message = trim($_GET['message'] ?? '');

    if (!$groupId || !$message) {
        echo "data: " . json_encode(['error' => 'Parâmetros inválidos.']) . "\n\n";
        flush();
        exit;
    }

    // Rate limiting
    $sessionId = session_id();
    $rateRow   = $pdo->prepare("SELECT * FROM rate_limits WHERE session_id=?");
    $rateRow->execute([$sessionId]);
    $rate = $rateRow->fetch();

    if ($rate) {
        $windowStart = strtotime($rate['window_start']);
        if (time() - $windowStart < 60) {
            if ($rate['requests'] >= 30) {
                echo "data: " . json_encode(['error' => 'Limite de requisições atingido. Aguarde.']) . "\n\n";
                flush();
                exit;
            }
            $pdo->prepare("UPDATE rate_limits SET requests=requests+1 WHERE session_id=?")
                ->execute([$sessionId]);
        } else {
            $pdo->prepare("UPDATE rate_limits SET requests=1, window_start=CURRENT_TIMESTAMP WHERE session_id=?")
                ->execute([$sessionId]);
        }
    } else {
        $pdo->prepare("INSERT INTO rate_limits (session_id, requests) VALUES (?,1)")->execute([$sessionId]);
    }

    // Load group (verify ownership)
    $grpStmt = $pdo->prepare("SELECT * FROM groups WHERE id = ? AND user_id = ?");
    $grpStmt->execute([$groupId, $userId]);
    $group = $grpStmt->fetch();
    if (!$group) {
        echo "data: " . json_encode(['error' => 'Grupo não encontrado.']) . "\n\n";
        flush();
        exit;
    }

    // Load members
    $mStmt = $pdo->prepare("
        SELECT c.* FROM group_members gm
        JOIN characters c ON c.id = gm.character_id
        WHERE gm.group_id = ?
    ");
    $mStmt->execute([$groupId]);
    $members = $mStmt->fetchAll();

    if (empty($members)) {
        echo "data: " . json_encode(['error' => 'O grupo não tem membros.']) . "\n\n";
        flush();
        exit;
    }

    // Load ai_config
    $config = $pdo->query("SELECT * FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
    if (!$config) {
        echo "data: " . json_encode(['error' => 'IA não configurada.']) . "\n\n";
        flush();
        exit;
    }

    // Save user message
    $pdo->prepare("
        INSERT INTO group_messages (group_id, user_id, sender_type, content)
        VALUES (?, ?, 'user', ?)
    ")->execute([$groupId, $userId, $message]);

    // Load last 30 group messages for context
    $histStmt = $pdo->prepare("
        SELECT * FROM group_messages
        WHERE group_id = ?
        ORDER BY created_at DESC LIMIT 30
    ");
    $histStmt->execute([$groupId]);
    $contextHistory = array_reverse($histStmt->fetchAll());

    // Select responding characters
    $responding = selectRespondingCharacters($members, $group);

    $model = determineModel($config);

    $prevChar = null; // track the last character that responded for reply-to chaining

    foreach ($responding as $idx => $character) {
        // Emit typing indicator
        echo "data: " . json_encode([
            'typing_char'      => true,
            'character_id'     => (int)$character['id'],
            'character_name'   => $character['name'],
            'character_avatar' => $character['avatar'] ?? null,
        ]) . "\n\n";
        flush();

        $systemPrompt = buildGroupSystemPrompt($character, $group, $members, $userName);
        $history      = buildGroupHistory($contextHistory, $character);

        // Determine what this character is replying to
        $replyToId      = null;
        $replyToName    = null;
        $replyToSnippet = null;

        if ($prevChar !== null) {
            // This character is replying to the previous character's message
            $lastCharMsg = end($contextHistory);
            if ($lastCharMsg && $lastCharMsg['sender_type'] === 'character') {
                $replyToId      = (int)($lastCharMsg['id'] ?? 0);
                $replyToName    = $lastCharMsg['character_name'];
                $replyToSnippet = mb_substr($lastCharMsg['content'], 0, 80);
                // Add instruction to reply to that character
                $systemPrompt .= "\n\nVocê está respondendo diretamente à mensagem de {$replyToName}: \"{$replyToSnippet}\"";
            }
        } elseif ($idx === 0) {
            // First character replies to user message
            $replyToName    = $userName;
            $replyToSnippet = mb_substr($message, 0, 80);
        }

        $charMeta = [
            'character_id'       => (int)$character['id'],
            'character_name'     => $character['name'],
            'character_avatar'   => $character['avatar'] ?? null,
            'reply_to_name'      => $replyToName,
            'reply_to_snippet'   => $replyToSnippet,
        ];

        $fullResponse = '';
        try {
            $fullResponse = streamGroupCharacter($config, $model, $systemPrompt, $history, $message, $character, $charMeta);
        } catch (Exception $e) {
            echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
            flush();
        }

        if ($fullResponse) {
            $stmt = $pdo->prepare("
                INSERT INTO group_messages (group_id, user_id, sender_type, character_id, character_name, content, reply_to_id, reply_to_name, reply_to_snippet)
                VALUES (?, ?, 'character', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId, $userId, (int)$character['id'], $character['name'], $fullResponse,
                $replyToId ?: null, $replyToName, $replyToSnippet,
            ]);
            $newMsgId = (int)$pdo->lastInsertId();

            $newEntry = [
                'id'             => $newMsgId,
                'sender_type'    => 'character',
                'character_id'   => (int)$character['id'],
                'character_name' => $character['name'],
                'content'        => $fullResponse,
                'reply_to_name'  => $replyToName,
                'reply_to_snippet' => $replyToSnippet,
            ];
            // Append to local context so subsequent characters see it
            $contextHistory[] = $newEntry;
            $prevChar = $character;
        }
    }

    echo "data: [DONE]\n\n";
    flush();
    exit;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(400);
echo json_encode(['error' => 'Ação inválida.']);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function buildSystemPrompt(array $character, string $userName): string {
    $name        = $character['name'];
    $description = $character['description'];
    $personality = $character['personality'];
    $voiceExample = $character['voice_example'];

    $prompt  = "Você é $name.";
    if ($description) {
        $prompt .= "\nDescrição: $description";
    }
    if ($personality) {
        $prompt .= "\nPersonalidade: $personality";
    }
    if ($voiceExample) {
        $prompt .= "\nExemplo de como falar: $voiceExample";
    }
    $prompt .= "\nVocê está conversando com $userName.";
    $prompt .= "\nResponda sempre em português do Brasil, de forma natural e coerente com sua personalidade.";
    $prompt .= "\nNão quebre o personagem.";
    return $prompt;
}

function determineModel(array $config): string {
    if ($config['model_mode'] === 'random') {
        $freeModels = [
            'openrouter' => [
                'google/gemma-3-27b-it:free',
                'meta-llama/llama-4-scout:free',
                'meta-llama/llama-4-maverick:free',
                'qwen/qwen3.6-plus-preview:free',
                'nvidia/nemotron-3-super-120b-a12b:free',
                'stepfun/step-3.5-flash:free',
                'mistralai/mistral-small-3.1-24b-instruct:free',
                'nvidia/nemotron-nano-12b-v2-vl:free',
            ],
            'groq'       => ['llama3-8b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it'],
            'gemini'     => ['gemini-1.5-flash', 'gemini-2.0-flash'],
            'ollama'     => ['llama3', 'mistral', 'gemma'],
            'openai'     => ['gpt-3.5-turbo'],
            'mistral'    => ['mistral-small-latest', 'open-mistral-7b'],
            'together'   => ['togethercomputer/llama-2-7b-chat'],
        ];
        $list = $freeModels[$config['provider']] ?? [$config['model']];
        return $list[array_rand($list)];
    }
    return $config['model'];
}

function streamAI(array $config, string $model, string $systemPrompt, array $history, string $userMessage, array $character): string {
    $provider = $config['provider'];

    if ($provider === 'gemini') {
        return streamGemini($config, $model, $systemPrompt, $history, $userMessage);
    }

    if ($provider === 'ollama') {
        return streamOllama($config, $model, $systemPrompt, $history, $userMessage);
    }

    return streamOpenAICompatible($config, $model, $systemPrompt, $history, $userMessage);
}

function streamOpenAICompatible(array $config, string $model, string $systemPrompt, array $history, string $userMessage): string {
    $url  = rtrim($config['base_url'], '/') . '/chat/completions';
    $msgs = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history,
        [['role' => 'user', 'content' => $userMessage]]
    );

    $body = json_encode([
        'model'      => $model,
        'messages'   => $msgs,
        'stream'     => true,
        'max_tokens' => 2000,
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key'],
    ];

    if ($config['provider'] === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'https://whatjuju.app');
        $headers[] = 'X-Title: What JUJU';
    }

    $fullResponse = '';
    $rawBody      = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$fullResponse, &$rawBody) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line || $line === 'data: [DONE]') continue;
                if (strpos($line, 'data: ') === 0) {
                    $json    = substr($line, 6);
                    $decoded = json_decode($json, true);
                    // Surface API-level errors sent inside the SSE stream
                    if (isset($decoded['error'])) {
                        $errMsg = is_array($decoded['error'])
                            ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                            : (string)$decoded['error'];
                        echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    $content = $decoded['choices'][0]['delta']['content'] ?? '';
                    if ($content) {
                        $fullResponse .= $content;
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                } else {
                    // Accumulate non-SSE lines (e.g. plain JSON error response)
                    $rawBody .= $line;
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Erro na conexão com IA: ' . $curlError);
    }

    if (!$fullResponse && $rawBody) {
        $decoded = json_decode($rawBody, true);
        $errMsg  = $decoded['error']['message']
            ?? $decoded['message']
            ?? null;
        throw new Exception($errMsg ?? 'Erro na API da IA (HTTP ' . $httpCode . '). Verifique as configurações.');
    }

    return $fullResponse;
}

function streamGemini(array $config, string $model, string $systemPrompt, array $history, string $userMessage): string {
    $apiKey = $config['api_key'];
    $url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$apiKey}&alt=sse";

    $contents = [];
    foreach ($history as $msg) {
        $geminiRole = $msg['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = ['role' => $geminiRole, 'parts' => [['text' => $msg['content']]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => $contents,
        'generationConfig'   => ['maxOutputTokens' => 2000],
    ]);

    $fullResponse = '';
    $buffer       = '';
    $rawBody      = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, &$buffer, &$rawBody) {
            $buffer .= $data;
            $lines   = explode("\n", $buffer);
            $buffer  = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                if (strpos($line, 'data: ') !== 0) {
                    // Accumulate non-SSE lines (e.g. plain JSON error response)
                    $rawBody .= $line;
                    continue;
                }
                $json    = substr($line, 6);
                $decoded = json_decode($json, true);
                // Surface API-level errors sent inside the SSE stream
                if (isset($decoded['error'])) {
                    $errMsg = is_array($decoded['error'])
                        ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                        : (string)$decoded['error'];
                    echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                    ob_flush();
                    flush();
                }
                $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($content) {
                    $fullResponse .= $content;
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Erro na conexão com IA: ' . $curlError);
    }

    if (!$fullResponse && $rawBody) {
        $decoded = json_decode($rawBody, true);
        $errMsg  = $decoded['error']['message']
            ?? $decoded['message']
            ?? null;
        throw new Exception($errMsg ?? 'Erro na API do Gemini (HTTP ' . $httpCode . '). Verifique as configurações.');
    }

    return $fullResponse;
}

function streamOllama(array $config, string $model, string $systemPrompt, array $history, string $userMessage): string {
    $baseUrl = rtrim($config['base_url'] ?: 'http://localhost:11434', '/');
    $url     = $baseUrl . '/api/chat';

    $msgs = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history,
        [['role' => 'user', 'content' => $userMessage]]
    );

    $body = json_encode([
        'model'    => $model,
        'messages' => $msgs,
        'stream'   => true,
    ]);

    $fullResponse = '';
    $rawBody      = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, &$rawBody) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                $decoded = json_decode($line, true);
                if ($decoded === null) {
                    $rawBody .= $line;
                    continue;
                }
                // Ollama error response
                if (isset($decoded['error'])) {
                    $errMsg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                    echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                    ob_flush();
                    flush();
                }
                $content = $decoded['message']['content'] ?? '';
                if ($content) {
                    $fullResponse .= $content;
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Erro na conexão com Ollama: ' . $curlError);
    }

    if (!$fullResponse && $rawBody) {
        $decoded = json_decode($rawBody, true);
        $errMsg  = $decoded['error'] ?? $decoded['message'] ?? null;
        throw new Exception(is_string($errMsg) ? $errMsg : 'Erro no Ollama (HTTP ' . $httpCode . '). Verifique as configurações.');
    }

    return $fullResponse;
}

function selectRespondingCharacters(array $members, array $group): array {
    if (empty($members)) return [];
    $mode = $group['interaction_mode'] ?? 'random';
    if ($mode === 'story') {
        return $members;
    }
    $shuffled = $members;
    shuffle($shuffled);
    $count = min(count($shuffled), rand(1, min(3, count($shuffled))));
    return array_slice($shuffled, 0, $count);
}

function buildGroupSystemPrompt(array $character, array $group, array $allMembers, string $userName): string {
    $name = $character['name'];
    $prompt = "Você é {$name}, um personagem de IA num grupo de chat chamado \"{$group['name']}\".";
    if ($character['description']) $prompt .= "\nDescrição: {$character['description']}";
    if ($character['personality']) $prompt .= "\nPersonalidade: {$character['personality']}";
    if ($character['voice_example']) $prompt .= "\nExemplo de como fala: {$character['voice_example']}";

    $others = array_filter($allMembers, fn($m) => (int)$m['id'] !== (int)$character['id']);
    if (!empty($others)) {
        $otherNames = array_map(fn($m) => $m['name'] . ($m['description'] ? " ({$m['description']})" : ''), $others);
        $prompt .= "\n\nOutros personagens no grupo: " . implode(', ', array_values($otherNames)) . ".";
    }

    if ($group['description']) $prompt .= "\nSobre o grupo: {$group['description']}";

    if (!empty($group['story'])) {
        $prompt .= "\n\nROTEIRO DA HISTÓRIA:\n{$group['story']}";
        $prompt .= "\n\nIMPORTANTE sobre o roteiro:";
        $prompt .= "\n- Se o roteiro mencionar que seu personagem é controlado por outro (vilão, feitiço, tecnologia, etc.), FINJA estar sendo controlado — aja conforme esse controle enquanto a história exigir.";
        $prompt .= "\n- Crie a história colaborativamente com os outros personagens, reagindo ao que eles dizem.";
        $prompt .= "\n- Quando outro personagem disser algo que afete você na história, responda diretamente a ele de forma dramática e natural.";
        $prompt .= "\n- Siga o roteiro mas improvise detalhes criativos dentro do contexto.";
    }

    $mode = $group['interaction_mode'] ?? 'random';
    if ($mode === 'story') {
        $prompt .= "\n\nMODO ROTEIRO: Desenvolva a história de forma dramática e narrativa. Aja exatamente como seu personagem age nesta cena. Avance a trama.";
    } elseif ($mode === 'topic') {
        $prompt .= "\n\nMODO ASSUNTO: Foque no tópico atual e comente com a perspectiva do seu personagem.";
    } else {
        $prompt .= "\n\nMODO ALEATÓRIO: Interaja naturalmente como num grupo de amigos. Comente, concorde, discorde, faça perguntas ao grupo.";
    }

    $prompt .= "\n\nREGRAS OBRIGATÓRIAS:";
    $prompt .= "\n- Responda APENAS como {$name}. Não saia do personagem.";
    $prompt .= "\n- Respostas curtas a médias (1-4 frases), naturais, em português do Brasil.";
    $prompt .= "\n- Se estiver respondendo a outro personagem, mencione o nome dele na resposta.";
    $prompt .= "\n- O usuário humano se chama {$userName}. Trate-o como parte da história se o roteiro exigir.";
    return $prompt;
}

function buildGroupHistory(array $history, array $currentChar): array {
    $msgs = [];
    foreach ($history as $msg) {
        if ($msg['sender_type'] === 'user') {
            $msgs[] = ['role' => 'user', 'content' => $msg['content']];
        } else {
            $charId   = (int)($msg['character_id'] ?? 0);
            $charName = $msg['character_name'] ?? 'Personagem';
            if ($charId === (int)$currentChar['id']) {
                $msgs[] = ['role' => 'assistant', 'content' => $msg['content']];
            } else {
                $msgs[] = ['role' => 'user', 'content' => "[{$charName}]: {$msg['content']}"];
            }
        }
    }
    return $msgs;
}

function streamGroupCharacter(array $config, string $model, string $systemPrompt, array $history, string $userMessage, array $character, array $charMeta): string {
    $provider = $config['provider'];

    if ($provider === 'gemini') {
        return streamGroupGemini($config, $model, $systemPrompt, $history, $userMessage, $charMeta);
    }

    if ($provider === 'ollama') {
        return streamGroupOllama($config, $model, $systemPrompt, $history, $userMessage, $charMeta);
    }

    return streamGroupOpenAICompatible($config, $model, $systemPrompt, $history, $userMessage, $charMeta);
}

function streamGroupOpenAICompatible(array $config, string $model, string $systemPrompt, array $history, string $userMessage, array $charMeta): string {
    $url  = rtrim($config['base_url'], '/') . '/chat/completions';
    $msgs = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history,
        [['role' => 'user', 'content' => $userMessage]]
    );

    $body = json_encode([
        'model'      => $model,
        'messages'   => $msgs,
        'stream'     => true,
        'max_tokens' => 2000,
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key'],
    ];

    if ($config['provider'] === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'https://sete.app');
        $headers[] = 'X-Title: SETE';
    }

    $fullResponse = '';
    $rawBody      = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$fullResponse, &$rawBody, $charMeta) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line || $line === 'data: [DONE]') continue;
                if (strpos($line, 'data: ') === 0) {
                    $json    = substr($line, 6);
                    $decoded = json_decode($json, true);
                    if (isset($decoded['error'])) {
                        $errMsg = is_array($decoded['error'])
                            ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                            : (string)$decoded['error'];
                        echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    $content = $decoded['choices'][0]['delta']['content'] ?? '';
                    if ($content) {
                        $fullResponse .= $content;
                        echo "data: " . json_encode(['content' => $content, 'char' => $charMeta]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                } else {
                    $rawBody .= $line;
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        throw new Exception('Erro na conexão com IA: ' . $error);
    }

    if (!$fullResponse && $rawBody) {
        $decoded = json_decode($rawBody, true);
        $errMsg  = $decoded['error']['message'] ?? $decoded['message'] ?? null;
        throw new Exception($errMsg ?? 'Erro na API da IA (HTTP ' . $httpCode . '). Verifique as configurações.');
    }

    return $fullResponse;
}

function streamGroupGemini(array $config, string $model, string $systemPrompt, array $history, string $userMessage, array $charMeta): string {
    $apiKey = $config['api_key'];
    $url    = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$apiKey}&alt=sse";

    $contents = [];
    foreach ($history as $msg) {
        $geminiRole = $msg['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = ['role' => $geminiRole, 'parts' => [['text' => $msg['content']]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $body = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => $contents,
        'generationConfig'   => ['maxOutputTokens' => 2000],
    ]);

    $fullResponse = '';
    $buffer       = '';
    $rawBody      = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, &$buffer, &$rawBody, $charMeta) {
            $buffer .= $data;
            $lines   = explode("\n", $buffer);
            $buffer  = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                if (strpos($line, 'data: ') !== 0) {
                    $rawBody .= $line;
                    continue;
                }
                $json    = substr($line, 6);
                $decoded = json_decode($json, true);
                if (isset($decoded['error'])) {
                    $errMsg = is_array($decoded['error'])
                        ? ($decoded['error']['message'] ?? json_encode($decoded['error']))
                        : (string)$decoded['error'];
                    echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                    ob_flush();
                    flush();
                }
                $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($content) {
                    $fullResponse .= $content;
                    echo "data: " . json_encode(['content' => $content, 'char' => $charMeta]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Erro na conexão com IA: ' . $curlError);
    }

    if (!$fullResponse && $rawBody) {
        $decoded = json_decode($rawBody, true);
        $errMsg  = $decoded['error']['message'] ?? $decoded['message'] ?? null;
        throw new Exception($errMsg ?? 'Erro na API do Gemini (HTTP ' . $httpCode . '). Verifique as configurações.');
    }

    return $fullResponse;
}

function streamGroupOllama(array $config, string $model, string $systemPrompt, array $history, string $userMessage, array $charMeta): string {
    $baseUrl = rtrim($config['base_url'] ?: 'http://localhost:11434', '/');
    $url     = $baseUrl . '/api/chat';

    $msgs = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history,
        [['role' => 'user', 'content' => $userMessage]]
    );

    $body = json_encode([
        'model'    => $model,
        'messages' => $msgs,
        'stream'   => true,
    ]);

    $fullResponse = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, $charMeta) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                $decoded = json_decode($line, true);
                if ($decoded === null) continue;
                if (isset($decoded['error'])) {
                    $errMsg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                    echo "data: " . json_encode(['error' => $errMsg]) . "\n\n";
                    ob_flush();
                    flush();
                }
                $content = $decoded['message']['content'] ?? '';
                if ($content) {
                    $fullResponse .= $content;
                    echo "data: " . json_encode(['content' => $content, 'char' => $charMeta]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    curl_close($ch);

    return $fullResponse;
}
