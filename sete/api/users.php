<?php
/**
 * WhatsappJUJU — CRUD de Usuários (admin)
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? filter_input(INPUT_GET,  'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'create':
        handleCreate();
        break;
    case 'update':
        handleUpdate();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'stats':
        handleStats();
        break;
    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}

// ──────────────────────────────────────────────────────────────────────────────

function handleList(): void {
    $search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    $db  = getDB();
    $sql = "SELECT id, name, email, role, status, avatar, created_at, last_seen FROM users";
    $params = [];

    if ($search) {
        $sql .= " WHERE name LIKE :s OR email LIKE :s2";
        $params[':s']  = "%{$search}%";
        $params[':s2'] = "%{$search}%";
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    jsonResponse(['success' => true, 'users' => $users]);
}

function handleGet(): void {
    $id = (int)filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    if (!$id) {
        jsonResponse(['error' => 'ID obrigatório'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role, status, avatar, created_at, last_seen FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['error' => 'Usuário não encontrado'], 404);
    }

    jsonResponse(['success' => true, 'user' => $user]);
}

function handleCreate(): void {
    $name     = trim(filter_input(INPUT_POST, 'name',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
    $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT) ?? '');
    $role     = trim(filter_input(INPUT_POST, 'role',     FILTER_SANITIZE_SPECIAL_CHARS) ?? 'user');

    if (!$name || !$email || !$password) {
        jsonResponse(['error' => 'Preencha todos os campos obrigatórios'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'E-mail inválido'], 400);
    }

    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Senha deve ter no mínimo 6 caracteres'], 400);
    }

    $role = in_array($role, ['admin', 'user']) ? $role : 'user';

    $db  = getDB();
    $chk = $db->prepare("SELECT id FROM users WHERE email = :email");
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        jsonResponse(['error' => 'E-mail já cadastrado'], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :pw, :role)");
    $stmt->execute([':name' => $name, ':email' => $email, ':pw' => $hash, ':role' => $role]);

    jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
}

function handleUpdate(): void {
    $id    = (int)filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $name  = trim(filter_input(INPUT_POST, 'name',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $role  = trim(filter_input(INPUT_POST, 'role',  FILTER_SANITIZE_SPECIAL_CHARS) ?? 'user');
    $password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT) ?? '');

    if (!$id || !$name || !$email) {
        jsonResponse(['error' => 'Campos obrigatórios faltando'], 400);
    }

    $role = in_array($role, ['admin', 'user']) ? $role : 'user';

    $db = getDB();

    // Verifica se email já pertence a outro usuário
    $chk = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $chk->execute([':email' => $email, ':id' => $id]);
    if ($chk->fetch()) {
        jsonResponse(['error' => 'E-mail já cadastrado por outro usuário'], 409);
    }

    if ($password) {
        if (strlen($password) < 6) {
            jsonResponse(['error' => 'Senha deve ter no mínimo 6 caracteres'], 400);
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, role = :role, password = :pw WHERE id = :id");
        $stmt->execute([':name' => $name, ':email' => $email, ':role' => $role, ':pw' => $hash, ':id' => $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id");
        $stmt->execute([':name' => $name, ':email' => $email, ':role' => $role, ':id' => $id]);
    }

    jsonResponse(['success' => true]);
}

function handleDelete(): void {
    $id         = (int)filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $adminEmail = $_SESSION['user_email'] ?? '';

    if (!$id) {
        jsonResponse(['error' => 'ID obrigatório'], 400);
    }

    // Impede deletar a si mesmo
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        jsonResponse(['error' => 'Não é possível deletar sua própria conta'], 400);
    }

    $db = getDB();
    // Impede deletar único admin
    $chk = $db->prepare("SELECT email FROM users WHERE id = :id");
    $chk->execute([':id' => $id]);
    $target = $chk->fetch();
    if (!$target) {
        jsonResponse(['error' => 'Usuário não encontrado'], 404);
    }

    $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);

    jsonResponse(['success' => true]);
}

function handleStats(): void {
    $db = getDB();

    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalChars = $db->query("SELECT COUNT(*) FROM characters")->fetchColumn();
    $todayMsgs  = $db->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = DATE('now')")->fetchColumn();
    $aiConf     = $db->query("SELECT provider, model FROM ai_config WHERE id = 1")->fetch();

    $lastLogins = $db->query("SELECT name, email, last_seen FROM users ORDER BY last_seen DESC LIMIT 5")->fetchAll();

    jsonResponse([
        'success'      => true,
        'total_users'  => (int)$totalUsers,
        'total_chars'  => (int)$totalChars,
        'today_msgs'   => (int)$todayMsgs,
        'ai_provider'  => $aiConf['provider'] ?? '',
        'ai_model'     => $aiConf['model']    ?? '',
        'last_logins'  => $lastLogins,
    ]);
}
