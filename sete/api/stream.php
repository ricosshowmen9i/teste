<?php
/**
 * WhatsappJUJU — SSE Streaming da IA
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/chat_helpers.php';

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

$user = requireLogin();

$convId  = (int)filter_input(INPUT_GET, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
$content = trim(filter_input(INPUT_GET, 'content', FILTER_DEFAULT) ?? '');
$msgType = filter_input(INPUT_GET, 'message_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'text';
$fileUrl = filter_input(INPUT_GET, 'file_url',  FILTER_SANITIZE_URL) ?? null;
$fileName= filter_input(INPUT_GET, 'file_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null;

if (!$convId || !$content) {
    sseEvent('error', json_encode(['message' => 'Parâmetros inválidos']));
    exit;
}

$db   = getDB();
$conv = getConversation($db, $convId, $user['id']);
if (!$conv) {
    sseEvent('error', json_encode(['message' => 'Conversa não encontrada']));
    exit;
}

// Salva mensagem do usuário
$msgId = saveMessage($db, $convId, 'user', $content, $msgType, $fileUrl, $fileName);
$db->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")
   ->execute([':id' => $convId]);

sseEvent('user_message', json_encode(['id' => $msgId]));

// Personagem e configuração
$character = getCharacter($db, $conv['character_id']);
$aiConfig  = getAIConfig($db);
$history   = getHistory($db, $convId, $character['memory_context']);

// Stream da resposta
$provider = $aiConfig['provider'] ?? 'openrouter';
$apiKey   = $aiConfig['api_key']  ?? '';
$baseUrl  = rtrim($aiConfig['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
$model    = selectModel($aiConfig);

// Monta mensagens
$messages = [['role' => 'system', 'content' => buildSystemPrompt($character, $user)]];
foreach ($history as $msg) {
    $role       = ($msg['role'] === 'assistant') ? 'assistant' : 'user';
    $messages[] = ['role' => $role, 'content' => $msg['content']];
}

$fullResponse = '';

if ($provider === 'ollama') {
    $fullResponse = streamOllama($messages, $model);
} elseif ($provider === 'gemini') {
    $fullResponse = streamGemini($messages, $apiKey, $model);
} else {
    $fullResponse = streamOpenAICompat($messages, $apiKey, $baseUrl, $model);
}

if ($fullResponse === null || $fullResponse === '') {
    $fullResponse = '[Erro ao obter resposta da IA]';
}

// Salva a resposta completa no banco
$aiMsgId = saveMessage($db, $convId, 'assistant', $fullResponse, 'text');
$db->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")
   ->execute([':id' => $convId]);

sseEvent('done', json_encode(['id' => $aiMsgId, 'content' => $fullResponse]));
exit;

// ──────────────────────────────────────────────────────────────────────────────

function sseEvent(string $event, string $data): void {
    echo "event: {$event}\n";
    echo "data: {$data}\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

function streamOpenAICompat(array $messages, string $apiKey, string $baseUrl, string $model): ?string {
    $payload = json_encode([
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => 2000,
        'stream'     => true,
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $fullText = '';
    $buffer   = '';

    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT    => 120,
        CURLOPT_WRITEFUNCTION => function($curl, $data) use (&$fullText, &$buffer) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line   = trim($line);

                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') {
                        break;
                    }
                    $chunk = json_decode($jsonStr, true);
                    $token = $chunk['choices'][0]['delta']['content'] ?? '';
                    if ($token !== '') {
                        $fullText .= $token;
                        sseEvent('token', json_encode(['token' => $token]));
                    }
                }
            }
            return strlen($data);
        },
    ]);

    curl_exec($ch);
    curl_close($ch);

    return $fullText ?: null;
}

function streamOllama(array $messages, string $model): ?string {
    $payload  = json_encode(['model' => $model, 'messages' => $messages, 'stream' => true]);
    $fullText = '';

    $ch = curl_init('http://localhost:11434/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT    => 120,
        CURLOPT_WRITEFUNCTION => function($curl, $data) use (&$fullText) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!$line) continue;
                $chunk = json_decode($line, true);
                $token = $chunk['message']['content'] ?? '';
                if ($token !== '') {
                    $fullText .= $token;
                    sseEvent('token', json_encode(['token' => $token]));
                }
            }
            return strlen($data);
        },
    ]);

    curl_exec($ch);
    curl_close($ch);

    return $fullText ?: null;
}

function streamGemini(array $messages, string $apiKey, string $model): ?string {
    $parts = [];
    $systemPrompt = '';
    foreach ($messages as $msg) {
        if ($msg['role'] === 'system') {
            $systemPrompt = $msg['content'];
            continue;
        }
        $role    = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $parts[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
    }

    if (empty($parts)) {
        $parts[] = ['role' => 'user', 'parts' => [['text' => 'Olá']]];
    }

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => $parts,
        'generationConfig'   => ['maxOutputTokens' => 2000],
    ]);

    $url      = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$apiKey}";
    $fullText = '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT    => 120,
        CURLOPT_WRITEFUNCTION => function($curl, $data) use (&$fullText) {
            $token = extractGeminiToken($data);
            if ($token !== '') {
                $fullText .= $token;
                sseEvent('token', json_encode(['token' => $token]));
            }
            return strlen($data);
        },
    ]);

    curl_exec($ch);
    curl_close($ch);

    return $fullText ?: null;
}

function extractGeminiToken(string $raw): string {
    preg_match_all('/"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $m);
    if (!empty($m[1])) {
        return implode('', array_map(function($t) {
            return json_decode('"' . $t . '"') ?? $t;
        }, $m[1]));
    }
    return '';
}
