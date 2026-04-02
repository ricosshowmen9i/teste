<?php
/**
 * WhatsappJUJU — Configurações de IA (admin) + Perfil do usuário
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireLogin();
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? filter_input(INPUT_GET,  'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? '';

switch ($action) {
    case 'save_ai':
        requireAdmin();
        handleSaveAI();
        break;
    case 'test_ai':
        requireAdmin();
        handleTestAI();
        break;
    case 'get_ai':
        requireAdmin();
        handleGetAI();
        break;
    case 'update_profile':
        handleUpdateProfile($user);
        break;
    case 'update_theme':
        handleUpdateTheme($user);
        break;
    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}

// ──────────────────────────────────────────────────────────────────────────────

function handleSaveAI(): void {
    $provider   = trim(filter_input(INPUT_POST, 'provider',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $apiKey     = trim(filter_input(INPUT_POST, 'api_key',     FILTER_DEFAULT) ?? '');
    $baseUrl    = trim(filter_input(INPUT_POST, 'base_url',    FILTER_SANITIZE_URL) ?? '');
    $model      = trim(filter_input(INPUT_POST, 'model',       FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $modelMode  = trim(filter_input(INPUT_POST, 'model_mode',  FILTER_SANITIZE_SPECIAL_CHARS) ?? 'random');

    $validProviders = ['openrouter', 'groq', 'gemini', 'ollama', 'openai', 'mistral', 'together'];
    if (!in_array($provider, $validProviders)) {
        jsonResponse(['error' => 'Provider inválido'], 400);
    }

    $modelMode = in_array($modelMode, ['random', 'fixed']) ? $modelMode : 'random';

    $db   = getDB();
    $stmt = $db->prepare("
        UPDATE ai_config
        SET provider = :provider, api_key = :api_key, base_url = :base_url,
            model = :model, model_mode = :model_mode, updated_at = CURRENT_TIMESTAMP
        WHERE id = 1
    ");
    $stmt->execute([
        ':provider'   => $provider,
        ':api_key'    => $apiKey,
        ':base_url'   => $baseUrl,
        ':model'      => $model,
        ':model_mode' => $modelMode,
    ]);

    jsonResponse(['success' => true, 'message' => 'Configuração salva!']);
}

function handleTestAI(): void {
    $db     = getDB();
    $config = $db->query("SELECT * FROM ai_config WHERE id = 1")->fetch();

    if (!$config || !$config['api_key']) {
        jsonResponse(['success' => false, 'message' => 'API Key não configurada']);
    }

    $provider = $config['provider'];
    $apiKey   = $config['api_key'];
    $baseUrl  = rtrim($config['base_url'], '/');
    $model    = $config['model'];

    $testMessages = [
        ['role' => 'user', 'content' => 'Responda apenas "ok" para confirmar que está funcionando.']
    ];

    if ($provider === 'ollama') {
        $payload = json_encode(['model' => $model, 'messages' => $testMessages, 'stream' => false]);
        $ch = curl_init('http://localhost:11434/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
    } elseif ($provider === 'gemini') {
        $payload = json_encode([
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Responda apenas "ok".']]]],
        ]);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
    } else {
        $payload = json_encode(['model' => $model, 'messages' => $testMessages, 'max_tokens' => 10, 'stream' => false]);
        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
    }

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        jsonResponse(['success' => false, 'message' => 'Erro de conexão: ' . $err]);
    }

    $data = json_decode($resp, true);

    if ($code >= 400) {
        $errMsg = $data['error']['message'] ?? $data['error'] ?? 'Erro HTTP ' . $code;
        jsonResponse(['success' => false, 'message' => $errMsg]);
    }

    jsonResponse(['success' => true, 'message' => 'Conexão OK! Modelo respondeu com sucesso.']);
}

function handleGetAI(): void {
    $db     = getDB();
    $config = $db->query("SELECT provider, base_url, model, model_mode, updated_at FROM ai_config WHERE id = 1")->fetch();
    // Não retorna api_key por segurança
    if ($config) {
        // Garante valor padrão para model_mode
        $config['model_mode'] = $config['model_mode'] ?? 'random';
    }
    jsonResponse(['success' => true, 'config' => $config]);
}

function handleUpdateProfile(array $user): void {
    $name   = trim(filter_input(INPUT_POST, 'name',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $status = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $avatar = trim(filter_input(INPUT_POST, 'avatar', FILTER_SANITIZE_URL) ?? '');

    if (!$name) {
        jsonResponse(['error' => 'Nome é obrigatório'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("UPDATE users SET name = :name, status = :status, avatar = :avatar WHERE id = :id");
    $stmt->execute([
        ':name'   => $name,
        ':status' => $status,
        ':avatar' => $avatar ?: null,
        ':id'     => $user['id'],
    ]);

    $_SESSION['user_name'] = $name;

    jsonResponse(['success' => true, 'message' => 'Perfil atualizado!']);
}

function handleUpdateTheme(array $user): void {
    $theme = trim(filter_input(INPUT_POST, 'theme', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $valid = ['verde', 'dark_blue', 'dark_orange', 'rosa', 'light'];

    if (!in_array($theme, $valid)) {
        jsonResponse(['error' => 'Tema inválido'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("UPDATE users SET theme = :theme WHERE id = :id");
    $stmt->execute([':theme' => $theme, ':id' => $user['id']]);

    jsonResponse(['success' => true, 'theme' => $theme]);
}
