<?php

session_start();

require_once __DIR__ . '/../db/init.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Erro no upload: ' . $file['error']]);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedDocs   = [
    'application/pdf',
    'text/plain',
    'text/csv',
    'application/json',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/markdown',
];
$allowedCode   = [
    'text/javascript',
    'application/x-php',
    'text/x-php',
    'text/x-python',
    'text/html',
    'text/css',
    'text/x-script.python',
    'application/x-httpd-php',
];

$maxSizeImages = 5 * 1024 * 1024;  // 5 MB
$maxSizeDocs   = 10 * 1024 * 1024; // 10 MB
$maxSizeCode   = 2 * 1024 * 1024;  // 2 MB

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (in_array($mime, $allowedImages, true)) {
    if ($file['size'] > $maxSizeImages) {
        http_response_code(400);
        echo json_encode(['error' => 'Imagem muito grande. Máximo 5 MB.']);
        exit;
    }
    $category = 'image';
} elseif (in_array($mime, $allowedDocs, true) || in_array($ext, ['pdf', 'txt', 'docx', 'csv', 'json', 'md'], true)) {
    if ($file['size'] > $maxSizeDocs) {
        http_response_code(400);
        echo json_encode(['error' => 'Documento muito grande. Máximo 10 MB.']);
        exit;
    }
    $category = 'document';
} elseif (in_array($mime, $allowedCode, true) || in_array($ext, ['js', 'php', 'py', 'html', 'css'], true)) {
    if ($file['size'] > $maxSizeCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Arquivo de código muito grande. Máximo 2 MB.']);
        exit;
    }
    $category = 'code';
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de arquivo não permitido.']);
    exit;
}

$safeExt  = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$filename = uniqid('file_', true) . '.' . $safeExt;
$destDir  = __DIR__ . '/../uploads/files/';
$destPath = $destDir . $filename;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar arquivo.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'url'      => 'uploads/files/' . $filename,
    'filename' => htmlspecialchars($file['name']),
    'mime'     => $mime,
    'category' => $category,
]);
