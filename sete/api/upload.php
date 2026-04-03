<?php
/**
 * WhatsappJUJU — Upload de imagens e arquivos
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

// Verifica sessão antes de qualquer output
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Garantir que as pastas de upload existem
$baseDir = dirname(__DIR__);
$uploadDirs = [
    $baseDir . '/uploads',
    $baseDir . '/uploads/avatars',
    $baseDir . '/uploads/files',
];
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Auto-detecta qual campo de arquivo foi enviado
$fileField  = null;
$targetDir  = 'files';
$isAvatar   = false;

if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = 'avatar';
    $targetDir = 'avatars';
    $isAvatar  = true;
} elseif (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = 'image';
    $targetDir = 'avatars';
    $isAvatar  = true;
} elseif (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileField = 'file';
    $targetDir = 'files';
    $isAvatar  = false;
}

if ($fileField === null) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
    exit;
}

$file = $_FILES[$fileField];

// Verifica erro de upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor)',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente no servidor',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP',
    ];
    $errMsg = $uploadErrors[$file['error']] ?? ('Erro de upload: código ' . $file['error']);
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

// Extensão do arquivo original
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Tipos permitidos baseados em extensão (mais confiável que MIME em hospedagem compartilhada)
$allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedFileExts  = ['pdf', 'txt', 'md', 'csv', 'json', 'py', 'html', 'css', 'docx'];

if ($isAvatar) {
    if (!in_array($ext, $allowedImageExts)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de imagem não permitido: ' . $ext . '. Use: jpg, jpeg, png, gif, webp']);
        exit;
    }
    $maxSize = MAX_IMAGE_SIZE;
} else {
    // Para arquivos de chat: imagem OU documento/código
    if (in_array($ext, $allowedImageExts)) {
        $maxSize  = MAX_IMAGE_SIZE;
        $msgType  = 'image';
    } elseif (in_array($ext, $allowedFileExts)) {
        $maxSize  = MAX_DOC_SIZE;
        $msgType  = 'file';
    } else {
        echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido: ' . $ext]);
        exit;
    }
}

// Verifica tamanho
if ($file['size'] > $maxSize) {
    $mb = round($maxSize / 1024 / 1024);
    echo json_encode(['success' => false, 'error' => "Arquivo muito grande. Máximo: {$mb}MB"]);
    exit;
}

// Gera nome único para o arquivo
$newName  = uniqid('up_', true) . '.' . $ext;
$destPath = $baseDir . '/uploads/' . $targetDir . '/' . $newName;
$relUrl   = 'uploads/' . $targetDir . '/' . $newName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Falha ao mover arquivo para o destino']);
    exit;
}

// Garante .htaccess de proteção na pasta de destino
$htaccessPath = $baseDir . '/uploads/' . $targetDir . '/.htaccess';
if (!file_exists($htaccessPath)) {
    file_put_contents($htaccessPath, "Options -Indexes\n");
}

if ($isAvatar) {
    echo json_encode(['success' => true, 'url' => $relUrl]);
} else {
    echo json_encode([
        'success'       => true,
        'url'           => $relUrl,
        'original_name' => $file['name'],
        'message_type'  => $msgType ?? 'file',
    ]);
}

