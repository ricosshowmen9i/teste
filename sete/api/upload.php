<?php
/**
 * WhatsappJUJU — Upload de imagens e arquivos
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();

$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'file';

if ($type === 'avatar') {
    handleAvatarUpload($user);
} else {
    handleFileUpload($user);
}

// ──────────────────────────────────────────────────────────────────────────────

function handleAvatarUpload(array $user): void {
    if (empty($_FILES['avatar'])) {
        jsonResponse(['error' => 'Nenhum arquivo enviado'], 400);
    }

    $file = $_FILES['avatar'];
    validateFile($file, ALLOWED_IMAGE_TYPES, MAX_IMAGE_SIZE);

    $dir = UPLOAD_PATH . '/avatars/';
    ensureDir($dir);

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('avatar_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['error' => 'Falha ao salvar arquivo'], 500);
    }

    $relUrl = 'uploads/avatars/' . $filename;

    jsonResponse(['success' => true, 'url' => $relUrl]);
}

function handleFileUpload(array $user): void {
    if (empty($_FILES['file'])) {
        jsonResponse(['error' => 'Nenhum arquivo enviado'], 400);
    }

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Determina tipo e limite
    $mime = getMimeType($file['tmp_name']);

    if (in_array($mime, ALLOWED_IMAGE_TYPES)) {
        $maxSize  = MAX_IMAGE_SIZE;
        $msgType  = 'image';
    } elseif (in_array($ext, ALLOWED_DOC_EXTS)) {
        $maxSize  = MAX_DOC_SIZE;
        $msgType  = 'file';
    } elseif (in_array($ext, ALLOWED_CODE_EXTS)) {
        $maxSize  = MAX_CODE_SIZE;
        $msgType  = 'file';
    } else {
        jsonResponse(['error' => 'Tipo de arquivo não permitido'], 400);
    }

    if ($file['size'] > $maxSize) {
        $mb = round($maxSize / 1024 / 1024);
        jsonResponse(['error' => "Arquivo muito grande. Máximo: {$mb}MB"], 400);
    }

    $dir = UPLOAD_PATH . '/files/';
    ensureDir($dir);

    $filename = uniqid('file_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['error' => 'Falha ao salvar arquivo'], 500);
    }

    $relUrl = 'uploads/files/' . $filename;

    jsonResponse([
        'success'      => true,
        'url'          => $relUrl,
        'original_name'=> htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'),
        'message_type' => $msgType,
        'mime'         => $mime,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────

function validateFile(array $file, array $allowedMimes, int $maxSize): void {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor)',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco',
        ];
        $msg = $errors[$file['error']] ?? 'Erro de upload';
        jsonResponse(['error' => $msg], 400);
    }

    if ($file['size'] > $maxSize) {
        $mb = round($maxSize / 1024 / 1024);
        jsonResponse(['error' => "Arquivo muito grande. Máximo: {$mb}MB"], 400);
    }

    $mime = getMimeType($file['tmp_name']);
    if (!in_array($mime, $allowedMimes)) {
        jsonResponse(['error' => 'Tipo de arquivo não permitido'], 400);
    }
}

function getMimeType(string $path): string {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime;
    }
    return mime_content_type($path) ?: 'application/octet-stream';
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Garante .htaccess de proteção
    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n");
    }
}
