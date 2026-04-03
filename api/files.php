<?php

session_start();

require_once __DIR__ . '/../db/init.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$fileParam = $_GET['file'] ?? '';

if (!$fileParam) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetro file é obrigatório.']);
    exit;
}

// Sanitize path - prevent directory traversal
$fileParam = ltrim($fileParam, '/');
$fileParam = str_replace(['../', '..\\', '..'], '', $fileParam);

$basePath    = __DIR__ . '/../';
$uploadsPath = realpath($basePath . 'uploads');
$fullPath    = realpath($basePath . $fileParam);

if ($fullPath === false || $uploadsPath === false || strpos($fullPath, $uploadsPath . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

$uploadFilesPath = realpath($basePath . 'uploads/files');

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Arquivo não encontrado.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fullPath);
$ext   = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

if (strpos($mime, 'image/') === 0) {
    $data = base64_encode(file_get_contents($fullPath));
    echo json_encode([
        'type'    => 'image',
        'mime'    => $mime,
        'content' => $data,
    ]);
    exit;
}

$textTypes = [
    'text/', 'application/json', 'application/javascript',
];
$isText = false;
foreach ($textTypes as $t) {
    if (strpos($mime, $t) === 0) {
        $isText = true;
        break;
    }
}

if (in_array($ext, ['txt', 'md', 'csv', 'js', 'php', 'py', 'html', 'css', 'json'], true)) {
    $isText = true;
}

if ($isText) {
    $content = file_get_contents($fullPath);
    echo json_encode([
        'type'    => 'text',
        'mime'    => $mime,
        'content' => $content,
    ]);
    exit;
}

if ($mime === 'application/pdf' || $ext === 'pdf') {
    $content = '';
    $isPdfPathSafe = $uploadFilesPath !== false
        && strpos($fullPath, $uploadFilesPath . DIRECTORY_SEPARATOR) === 0
        && is_file($fullPath);

    if ($isPdfPathSafe && function_exists('shell_exec')) {
        $escaped = escapeshellarg($fullPath);
        $raw = shell_exec("pdftotext {$escaped} - 2>/dev/null");
        if (is_string($raw)) {
            $content = trim($raw);
        }
    }

    if ($content !== '') {
        echo json_encode([
            'type'    => 'text',
            'mime'    => $mime,
            'content' => $content,
        ]);
        exit;
    }

    echo json_encode([
        'type'    => 'pdf',
        'mime'    => $mime,
        'content' => '',
        'note'    => 'PDF enviado. Não foi possível extrair texto automaticamente.',
    ]);
    exit;
}

echo json_encode([
    'type' => 'binary',
    'mime' => $mime,
    'size' => filesize($fullPath),
]);
