<?php
/**
 * WhatsappJUJU — Leitura de arquivos enviados no chat
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user    = requireLogin();
$fileUrl = trim(filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '');

if (!$fileUrl) {
    jsonResponse(['error' => 'URL do arquivo obrigatória'], 400);
}

// Segurança: só permite arquivos dentro de uploads/
$fileUrl  = ltrim($fileUrl, '/');
$basePath = BASE_PATH . '/';
$fullPath = realpath($basePath . $fileUrl);

// Previne path traversal
if (!$fullPath || strpos($fullPath, realpath(UPLOAD_PATH)) !== 0) {
    jsonResponse(['error' => 'Acesso não permitido'], 403);
}

if (!file_exists($fullPath)) {
    jsonResponse(['error' => 'Arquivo não encontrado'], 404);
}

$ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$size = filesize($fullPath);

// Imagens: retorna base64
if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $mime  = $mimes[$ext] ?? 'image/jpeg';
    $data  = base64_encode(file_get_contents($fullPath));
    jsonResponse(['success' => true, 'type' => 'image', 'mime' => $mime, 'data' => $data]);
}

// Textos: retorna conteúdo
$textExts = ['txt', 'md', 'csv', 'json', 'js', 'php', 'py', 'html', 'css'];
if (in_array($ext, $textExts)) {
    if ($size > 500 * 1024) { // 500KB max para leitura
        jsonResponse(['error' => 'Arquivo muito grande para leitura'], 400);
    }
    $content = file_get_contents($fullPath);
    jsonResponse(['success' => true, 'type' => 'text', 'content' => $content, 'extension' => $ext]);
}

// PDF: tenta extração básica
if ($ext === 'pdf') {
    $content = extractPdfText($fullPath);
    jsonResponse(['success' => true, 'type' => 'pdf', 'content' => $content]);
}

// DOCX: extração básica de texto
if ($ext === 'docx') {
    $content = extractDocxText($fullPath);
    jsonResponse(['success' => true, 'type' => 'docx', 'content' => $content]);
}

jsonResponse(['error' => 'Tipo de arquivo não suportado para leitura'], 400);

// ──────────────────────────────────────────────────────────────────────────────

function extractPdfText(string $path): string {
    // Extração básica de texto de PDF sem dependências externas
    $content = file_get_contents($path);
    if (!$content) {
        return '[Não foi possível ler o PDF]';
    }

    // Extrai streams de texto
    $text = '';
    preg_match_all('/BT(.+?)ET/s', $content, $matches);
    foreach ($matches[1] as $bt) {
        preg_match_all('/\(([^)]*)\)\s*T[jJ]/', $bt, $textMatches);
        foreach ($textMatches[1] as $t) {
            $text .= $t . ' ';
        }
    }

    $text = preg_replace('/[^\x20-\x7E\xC0-\xFF]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    return $text ?: '[PDF sem texto extraível. Use um conversor para melhor leitura.]';
}

function extractDocxText(string $path): string {
    if (!class_exists('ZipArchive')) {
        return '[ZipArchive não disponível para ler DOCX]';
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '[Não foi possível abrir o DOCX]';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xml) {
        return '[Documento vazio]';
    }

    // Remove tags XML e extrai texto
    $text = strip_tags(str_replace(['</w:p>', '</w:r>'], ["\n", ' '], $xml));
    return trim($text) ?: '[Documento sem texto]';
}
