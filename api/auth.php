<?php

session_start();

require_once __DIR__ . '/../db/init.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Email e senha são obrigatórios.']);
        exit;
    }

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciais inválidas.']);
            exit;
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['theme']     = $user['theme'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$user['id']]);

        if ($user['force_password_change']) {
            echo json_encode(['force_change' => true]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'user'    => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'role'  => $user['role'],
                'theme' => $user['theme'],
            ],
        ]);
        exit;

    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno.']);
        exit;
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'change_password') {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado.']);
        exit;
    }

    $newPassword    = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'A senha deve ter pelo menos 6 caracteres.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'As senhas não coincidem.']);
        exit;
    }

    try {
        $pdo  = getDB();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?")
            ->execute([$hash, $_SESSION['user_id']]);

        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao alterar senha.']);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida.']);
