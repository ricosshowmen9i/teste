<?php
/**
 * WhatsappJUJU — Proxy ElevenLabs TTS
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

$user = requireLogin();

$text    = trim(filter_input(INPUT_POST, 'text',     FILTER_DEFAULT) ?? '');
$voiceId = trim(filter_input(INPUT_POST, 'voice_id', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$apiKey  = trim(filter_input(INPUT_POST, 'api_key',  FILTER_DEFAULT) ?? '');

if (!$text || !$voiceId || !$apiKey) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros obrigatórios: text, voice_id, api_key']);
    exit;
}

// Limita tamanho do texto
if (strlen($text) > 5000) {
    $text = substr($text, 0, 5000);
}

$payload = json_encode([
    'text'          => $text,
    'model_id'      => 'eleven_multilingual_v2',
    'voice_settings' => [
        'stability'        => 0.5,
        'similarity_boost' => 0.75,
    ],
]);

$url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'xi-api-key: ' . $apiKey,
        'Accept: audio/mpeg',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'Erro de conexão: ' . $err]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'ElevenLabs retornou HTTP ' . $httpCode]);
    exit;
}

// Retorna o áudio como base64
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'audio'   => base64_encode($response),
    'mime'    => 'audio/mpeg',
]);
