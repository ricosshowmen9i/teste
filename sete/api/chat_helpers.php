<?php
/**
 * WhatsappJUJU — Funções auxiliares de chat (compartilhadas)
 */
if (!defined('WHATSAPPJUJU')) { die(); }

function getConversation(PDO $db, int $convId, int $userId): ?array {
    $stmt = $db->prepare("SELECT * FROM conversations WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $convId, ':uid' => $userId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getCharacter(PDO $db, int $charId): ?array {
    $stmt = $db->prepare("SELECT * FROM characters WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $charId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function getAIConfig(PDO $db): array {
    $stmt = $db->prepare("SELECT * FROM ai_config WHERE id = 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        return [
            'provider'   => 'openrouter',
            'api_key'    => '',
            'base_url'   => 'https://openrouter.ai/api/v1',
            'model'      => 'mistralai/mistral-7b-instruct:free',
            'model_mode' => 'random',
        ];
    }
    // Garante campo model_mode com valor padrão
    $row['model_mode'] = $row['model_mode'] ?? 'random';
    return $row;
}

function getHistory(PDO $db, int $convId, int $limit): array {
    $stmt = $db->prepare("
        SELECT role, content, message_type, file_url FROM messages
        WHERE conversation_id = :cid
        ORDER BY created_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':cid', $convId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->execute();
    return array_reverse($stmt->fetchAll());
}

function saveMessage(PDO $db, int $convId, string $role, string $content, string $type = 'text', ?string $fileUrl = null, ?string $fileName = null): int {
    $stmt = $db->prepare("
        INSERT INTO messages (conversation_id, role, content, message_type, file_url, file_name)
        VALUES (:cid, :role, :content, :type, :furl, :fname)
    ");
    $stmt->execute([
        ':cid'     => $convId,
        ':role'    => $role,
        ':content' => $content,
        ':type'    => $type,
        ':furl'    => $fileUrl,
        ':fname'   => $fileName,
    ]);
    return (int)$db->lastInsertId();
}

function buildSystemPrompt(array $character, array $user): string {
    $name        = $character['name']          ?? 'Assistente';
    $personality = $character['personality']   ?? 'Seja prestativo e amigável.';
    $example     = $character['voice_example'] ?? '';
    $date        = date('d/m/Y H:i');

    $prompt = "Você é {$name}. {$personality}\n";
    if ($example) {
        $prompt .= "Fale sempre como: {$example}\n";
    }
    $prompt .= "Data atual: {$date}. O usuário se chama: {$user['name']}.\n";
    $prompt .= "Responda sempre em português do Brasil.";

    return $prompt;
}

/**
 * Seleciona o modelo de IA: aleatório (da lista gratuita) ou fixo (configurado).
 */
function selectModel(array $aiConfig): string {
    $modelMode = $aiConfig['model_mode'] ?? 'random';
    if ($modelMode === 'random' && defined('FREE_MODELS') && !empty(FREE_MODELS)) {
        $models = FREE_MODELS;
        return $models[array_rand($models)];
    }
    return $aiConfig['model'] ?? 'mistralai/mistral-7b-instruct:free';
}

function callAI(array $history, array $character, array $aiConfig, array $user): array {
    $provider = $aiConfig['provider'] ?? 'openrouter';
    $apiKey   = $aiConfig['api_key']  ?? '';
    $baseUrl  = rtrim($aiConfig['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
    $model    = selectModel($aiConfig);

    $messages = [['role' => 'system', 'content' => buildSystemPrompt($character, $user)]];
    foreach ($history as $msg) {
        $role       = ($msg['role'] === 'assistant') ? 'assistant' : 'user';
        $messages[] = ['role' => $role, 'content' => $msg['content']];
    }

    if ($provider === 'ollama') {
        return callOllama($messages, $model);
    } elseif ($provider === 'gemini') {
        return callGemini($messages, $apiKey, $model);
    } else {
        return callOpenAICompat($messages, $apiKey, $baseUrl, $model);
    }
}

function callOpenAICompat(array $messages, string $apiKey, string $baseUrl, string $model): array {
    $payload = json_encode([
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => 2000,
        'stream'     => false,
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $ch = curl_init($baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { return ['error' => 'Erro de conexão: ' . $err]; }

    $data = json_decode($resp, true);
    if (!$data)            { return ['error' => 'Resposta inválida da IA']; }
    if (isset($data['error'])) { return ['error' => $data['error']['message'] ?? 'Erro da API']; }

    return ['content' => $data['choices'][0]['message']['content'] ?? ''];
}

function callOllama(array $messages, string $model): array {
    $payload = json_encode(['model' => $model, 'messages' => $messages, 'stream' => false]);

    $ch = curl_init('http://localhost:11434/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { return ['error' => 'Ollama: ' . $err]; }

    $data = json_decode($resp, true);
    return ['content' => $data['message']['content'] ?? ''];
}

function callGemini(array $messages, string $apiKey, string $model): array {
    $parts        = [];
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

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { return ['error' => 'Gemini: ' . $err]; }

    $data    = json_decode($resp, true);
    $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['content' => $content];
}
