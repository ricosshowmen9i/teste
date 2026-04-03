<?php

session_start();

require_once __DIR__ . '/../db/init.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'profile') {
    $user = $pdo->prepare("SELECT id, name, email, role, avatar, status, theme, created_at, last_login FROM users WHERE id=?");
    $user->execute([$userId]);
    echo json_encode(['user' => $user->fetch()]);
    exit;
}

if ($method === 'GET') {
    $user = $pdo->prepare("SELECT id, name, email, role, avatar, status, theme, created_at, last_login FROM users WHERE id=?");
    $user->execute([$userId]);
    echo json_encode(['user' => $user->fetch()]);
    exit;
}

if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }

    if ($action === 'update') {
        $name   = trim($_POST['name'] ?? '');
        $status = trim($_POST['status'] ?? 'Disponível');
        $theme  = trim($_POST['theme'] ?? 'green');

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome é obrigatório.']);
            exit;
        }

        $allowedThemes = ['green', 'darkblue', 'pink', 'darkorange', 'light'];
        $theme = in_array($theme, $allowedThemes, true) ? $theme : 'green';

        $pdo->prepare("UPDATE users SET name=?, status=?, theme=? WHERE id=?")
            ->execute([$name, $status, $theme, $userId]);

        $_SESSION['name']  = $name;
        $_SESSION['theme'] = $theme;

        echo json_encode(['success' => true, 'theme' => $theme]);
        exit;
    }

    if ($action === 'upload_avatar') {
        if (empty($_FILES['avatar'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum arquivo enviado.']);
            exit;
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Erro no upload: ' . $file['error']]);
            exit;
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tipo de arquivo não permitido. Use jpg, jpeg, png, gif ou webp.']);
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo muito grande. Máximo 2MB.']);
            exit;
        }

        $safeExt  = preg_replace('/[^a-z0-9]/', '', $ext);
        $filename = 'avatar_' . $userId . '_' . uniqid() . '.' . $safeExt;
        $destDir  = __DIR__ . '/../uploads/avatars/';
        $dest     = $destDir . $filename;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Also ensure files dir exists
        $filesDir = __DIR__ . '/../uploads/files/';
        if (!is_dir($filesDir)) {
            mkdir($filesDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar arquivo.']);
            exit;
        }

        $url = 'uploads/avatars/' . $filename;
        $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$url, $userId]);

        echo json_encode(['success' => true, 'avatar' => $url]);
        exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $user->execute([$userId]);
        $row = $user->fetch();

        if (!password_verify($current, $row['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Senha atual incorreta.']);
            exit;
        }

        if (strlen($new) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Nova senha deve ter pelo menos 6 caracteres.']);
            exit;
        }

        if ($new !== $confirm) {
            http_response_code(400);
            echo json_encode(['error' => 'As senhas não coincidem.']);
            exit;
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida.']);
