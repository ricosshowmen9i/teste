<?php
/**
 * api/elevenlabs_tts.php
 * Proxy para ElevenLabs Text-to-Speech
 */

session_start();
require_once __DIR__ . '/../db/init.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

// Ler body JSON
$input    = json_decode(file_get_contents('php://input'), true);
$text     = trim($input['text'] ?? '');
$voiceId  = trim($input['voice_id'] ?? '');

if (!$text || !$voiceId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'text e voice_id são obrigatórios.']);
    exit;
}

// Buscar API key do ElevenLabs na config
$apiKey = '';
try {
    $pdo = getDB();
    $row = $pdo->query("SELECT elevenlabs_api_key FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
    $apiKey = $row['elevenlabs_api_key'] ?? ''; 
} catch (Exception $e) {
    // ignore
}

if (!$apiKey) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ElevenLabs API Key não configurada no painel Admin.']);
    exit;
}

$url  = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";
$body = json_encode([
    'text'          => $text,
    'model_id'      => 'eleven_multilingual_v2',
    'voice_settings' => [
        'stability'        => 0.5,
        'similarity_boost' => 0.75,
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'xi-api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: audio/mpeg',
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200) {
    $err = json_decode($response, true);
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => $err['detail']['message'] ?? 'Erro na API ElevenLabs (HTTP ' . $httpCode . ')']);
    exit;
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($response));
header('Cache-Control: no-store');
echo $response;
exit;
