<?php

ini_set('display_errors', '0');

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

// Auto-detect file field: check avatar, image, file
$fileField = null;
foreach (['avatar', 'image', 'file'] as $fieldName) {
    if (!empty($_FILES[$fieldName])) {
        $fileField = $fieldName;
        break;
    }
}

if ($fileField === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES[$fileField];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Erro no upload: ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$imageExts   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$docExts     = ['pdf', 'txt', 'docx', 'csv', 'json', 'md'];
$codeExts    = ['js', 'php', 'py', 'html', 'css'];

$maxSizeImages = 5 * 1024 * 1024;  // 5 MB
$maxSizeDocs   = 10 * 1024 * 1024; // 10 MB
$maxSizeCode   = 2 * 1024 * 1024;  // 2 MB

if (in_array($ext, $imageExts, true)) {
    if ($file['size'] > $maxSizeImages) {
        http_response_code(400);
        echo json_encode(['error' => 'Imagem muito grande. Máximo 5 MB.']);
        exit;
    }
    $category = 'image';
    $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
} elseif (in_array($ext, $docExts, true)) {
    if ($file['size'] > $maxSizeDocs) {
        http_response_code(400);
        echo json_encode(['error' => 'Documento muito grande. Máximo 10 MB.']);
        exit;
    }
    $category = 'document';
    $mime = 'application/octet-stream';
} elseif (in_array($ext, $codeExts, true)) {
    if ($file['size'] > $maxSizeCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Arquivo de código muito grande. Máximo 2 MB.']);
        exit;
    }
    $category = 'code';
    $mime = 'text/plain';
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de arquivo não permitido.']);
    exit;
}

// If field is avatar, save to avatars directory
$isAvatar = ($fileField === 'avatar');

$safeExt  = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$filename = uniqid('file_', true) . '.' . $safeExt;

// Ensure both directories exist
$avatarsDir = __DIR__ . '/../uploads/avatars/';
$filesDir   = __DIR__ . '/../uploads/files/';
if (!is_dir($avatarsDir)) mkdir($avatarsDir, 0755, true);
if (!is_dir($filesDir))   mkdir($filesDir, 0755, true);

if ($isAvatar && $category === 'image') {
    $filename = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $safeExt;
    $destDir  = $avatarsDir;
} else {
    $destDir  = $filesDir;
}
$destPath = $destDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar arquivo.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'url'      => ($isAvatar && $category === 'image' ? 'uploads/avatars/' : 'uploads/files/') . $filename,
    'filename' => htmlspecialchars($file['name']),
    'mime'     => $mime,
    'category' => $category,
]);
