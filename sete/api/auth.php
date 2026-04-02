<?php
/**
 * WhatsappJUJU — Autenticação (login, logout, registro)
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? filter_input(INPUT_GET,  'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'register':
        handleRegister();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}

// ──────────────────────────────────────────────────────────────────────────────

function handleLogin(): void {
    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
    $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT)        ?? '');

    if (!$email || !$password) {
        jsonResponse(['error' => 'Preencha e-mail e senha'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['error' => 'E-mail ou senha incorretos'], 401);
    }

    // Atualiza last_seen
    $upd = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = :id");
    $upd->execute([':id' => $user['id']]);

    // Salva sessão
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    jsonResponse([
        'success'               => true,
        'user'                  => [
            'id'                    => $user['id'],
            'name'                  => $user['name'],
            'email'                 => $user['email'],
            'role'                  => $user['role'],
            'avatar'                => $user['avatar'],
            'theme'                 => $user['theme'],
            'force_password_change' => (bool)$user['force_password_change'],
        ],
    ]);
}

function handleLogout(): void {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleRegister(): void {
    $name     = trim(filter_input(INPUT_POST, 'name',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
    $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT) ?? '');

    if (!$name || !$email || !$password) {
        jsonResponse(['error' => 'Preencha todos os campos'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'E-mail inválido'], 400);
    }

    if (strlen($password) < 6) {
        jsonResponse(['error' => 'A senha deve ter no mínimo 6 caracteres'], 400);
    }

    $db   = getDB();
    $chk  = $db->prepare("SELECT id FROM users WHERE email = :email");
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        jsonResponse(['error' => 'E-mail já cadastrado'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ins  = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
    $ins->execute([':name' => $name, ':email' => $email, ':password' => $hash]);

    jsonResponse(['success' => true, 'message' => 'Conta criada com sucesso!']);
}

function handleChangePassword(): void {
    $user = requireLogin();
    $newPassword = trim(filter_input(INPUT_POST, 'new_password', FILTER_DEFAULT) ?? '');

    if (strlen($newPassword) < 6) {
        jsonResponse(['error' => 'A nova senha deve ter no mínimo 6 caracteres'], 400);
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $db   = getDB();
    $upd  = $db->prepare("UPDATE users SET password = :pw, force_password_change = 0 WHERE id = :id");
    $upd->execute([':pw' => $hash, ':id' => $user['id']]);

    jsonResponse(['success' => true, 'message' => 'Senha alterada com sucesso!']);
}
