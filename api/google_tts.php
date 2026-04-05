<?php

session_start();

require_once __DIR__ . '/../db/init.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$text      = trim($input['text'] ?? '');
$voiceType = trim($input['voice_type'] ?? 'feminina_adulta');

if (!$text) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Texto vazio.']);
    exit;
}

$pdo    = getDB();
$config = $pdo->query("SELECT google_tts_api_key FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
$apiKey = $config['google_tts_api_key'] ?? '';

if (!$apiKey) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Google TTS API Key nao configurada']);
    exit;
}

$voiceMap = [
    'feminina_adulta'  => 'Aoede',
    'masculina_adulto' => 'Charon',
    'crianca_menina'   => 'Puck',
    'crianca_menino'   => 'Puck',
    'idosa'            => 'Kore',
    'idoso'            => 'Fenrir',
    'robotica'         => 'Orus',
];
$voiceName = $voiceMap[$voiceType] ?? 'Aoede';

$url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent?key=' . urlencode($apiKey);
$body = json_encode([
    'contents' => [
        ['parts' => [['text' => $text]]],
    ],
    'generationConfig' => [
        'responseModalities' => ['AUDIO'],
        'speechConfig'       => [
            'voiceConfig' => [
                'prebuiltVoiceConfig' => [
                    'voiceName' => $voiceName,
                ],
            ],
        ],
    ],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Erro de rede: ' . $curlErr]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    $decoded = json_decode($response, true);
    $errMsg  = $decoded['error']['message'] ?? substr($response, 0, 200);
    echo json_encode(['error' => "Google TTS HTTP $httpCode: $errMsg"]);
    exit;
}

$decoded = json_decode($response, true);
$audioB64 = $decoded['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? '';

if (!$audioB64) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Resposta de áudio vazia do Google TTS.']);
    exit;
}

$audioData = base64_decode($audioB64);
header('Content-Type: audio/wav');
header('Content-Length: ' . strlen($audioData));
echo $audioData;
