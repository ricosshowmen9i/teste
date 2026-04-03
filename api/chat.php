<?php

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
        $filePath = __DIR__ . '/../' . ltrim($fileUrl, '/');
        if (file_exists($filePath)) {
            $fileMime = mime_content_type($filePath);
            if (strpos($fileMime, 'text/') === 0 || in_array($fileMime, ['application/json', 'application/pdf'], true)) {
                $fileContent = file_get_contents($filePath);
                $userMessageContent .= "\n\n[Arquivo: $fileName]\n" . substr($fileContent, 0, 4000);
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
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$fullResponse) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line || $line === 'data: [DONE]') continue;
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    $decoded = json_decode($json, true);
                    $content = $decoded['choices'][0]['delta']['content'] ?? '';
                    if ($content) {
                        $fullResponse .= $content;
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
            }
            return strlen($data);
        },
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('Erro na conexão com IA: ' . $error);
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
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse, &$buffer) {
            $buffer .= $data;
            $lines   = explode("\n", $buffer);
            $buffer  = array_pop($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line || strpos($line, 'data: ') !== 0) continue;
                $json    = substr($line, 6);
                $decoded = json_decode($json, true);
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
    curl_close($ch);

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
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_POSTFIELDS    => $body,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullResponse) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                $decoded = json_decode($line, true);
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
    curl_close($ch);

    return $fullResponse;
}
