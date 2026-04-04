<?php

session_start();

require_once __DIR__ . '/../db/init.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'stats') {
        $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $charCount = $pdo->query("SELECT COUNT(*) FROM characters")->fetchColumn();
        $todayMsgs = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = DATE('now')")->fetchColumn();
        $cfg = $pdo->query("SELECT provider, model FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
        $lastLogins = $pdo->query("
            SELECT name, email, last_login FROM users
            WHERE last_login IS NOT NULL
            ORDER BY last_login DESC LIMIT 10
        ")->fetchAll();

        echo json_encode([
            'user_count'    => $userCount,
            'char_count'    => $charCount,
            'today_messages'=> $todayMsgs,
            'provider'      => $cfg['provider'] ?? null,
            'model'         => $cfg['model'] ?? null,
            'last_logins'   => $lastLogins,
        ]);
        exit;
    }

    if ($action === 'config') {
        $config = $pdo->query("SELECT * FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();
        echo json_encode(['config' => $config]);
        exit;
    }

    if ($action === 'users') {
        $users = $pdo->query("SELECT id, name, email, role, active, avatar, status, theme, created_at, last_login FROM users ORDER BY created_at DESC")->fetchAll();
        echo json_encode(['users' => $users]);
        exit;
    }
}

if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }

    if ($action === 'save_config') {
        $provider  = trim($_POST['provider'] ?? 'openrouter');
        $apiKey    = trim($_POST['api_key'] ?? '');
        $baseUrl   = trim($_POST['base_url'] ?? '');
        $model     = trim($_POST['model'] ?? '');
        $modelMode = trim($_POST['model_mode'] ?? 'fixed');

        $validProviders = ['openrouter', 'groq', 'openai', 'mistral', 'together', 'ollama', 'gemini'];
        if (!in_array($provider, $validProviders, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Provider inválido.']);
            exit;
        }

        $existing = $pdo->query("SELECT id FROM ai_config LIMIT 1")->fetch();
        if ($existing) {
            $pdo->prepare("
                UPDATE ai_config SET provider=?, api_key=?, base_url=?, model=?, model_mode=?, updated_at=CURRENT_TIMESTAMP
                WHERE id=?
            ")->execute([$provider, $apiKey, $baseUrl, $model, $modelMode, $existing['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO ai_config (provider, api_key, base_url, model, model_mode) VALUES (?,?,?,?,?)
            ")->execute([$provider, $apiKey, $baseUrl, $model, $modelMode]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'test_connection') {
        $config = $pdo->query("SELECT * FROM ai_config ORDER BY id DESC LIMIT 1")->fetch();

        if (!$config || !$config['api_key']) {
            echo json_encode(['success' => false, 'error' => 'API key não configurada.']);
            exit;
        }

        try {
            $url = rtrim($config['base_url'], '/') . '/models';
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $config['api_key'],
                    'Content-Type: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                echo json_encode(['success' => true, 'message' => 'Conexão bem sucedida!']);
            } else {
                echo json_encode(['success' => false, 'error' => "HTTP $httpCode: " . substr($response, 0, 200)]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'create_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'user';

        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos obrigatórios ausentes.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email inválido.']);
            exit;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Senha deve ter pelo menos 6 caracteres.']);
            exit;
        }

        $role = in_array($role, ['admin', 'user'], true) ? $role : 'user';

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                ->execute([$name, $email, $hash, $role]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Email já cadastrado.']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao criar usuário.']);
            }
        }
        exit;
    }

    if ($action === 'update_user') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $role   = $_POST['role'] ?? 'user';
        $active = (int)($_POST['active'] ?? 1);

        if (!$id || !$name || !$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos obrigatórios ausentes.']);
            exit;
        }

        $role = in_array($role, ['admin', 'user'], true) ? $role : 'user';

        try {
            $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, active=? WHERE id=?");
            $stmt->execute([$name, $email, $role, $active, $id]);

            if (!empty($_POST['password']) && strlen($_POST['password']) >= 6) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=?, force_password_change=0 WHERE id=?")
                    ->execute([$hash, $id]);
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao atualizar usuário.']);
        }
        exit;
    }

    if ($action === 'upload_logo') {
        if (empty($_FILES['logo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nenhum arquivo enviado.']);
            exit;
        }

        $file = $_FILES['logo'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (!in_array($mime, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tipo de arquivo não permitido. Use PNG, JPG ou SVG.']);
            exit;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Arquivo muito grande. Máximo 2MB.']);
            exit;
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'app_logo_' . uniqid() . '.' . $ext;
        $dest     = __DIR__ . '/../uploads/files/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao salvar arquivo.']);
            exit;
        }

        $url = 'uploads/files/' . $filename;
        $existing = $pdo->query("SELECT id FROM ai_config LIMIT 1")->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE ai_config SET app_logo=? WHERE id=?")->execute([$url, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO ai_config (app_logo) VALUES (?)")->execute([$url]);
        }

        echo json_encode(['success' => true, 'logo' => $url]);
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        // Prevent self-delete
        if ($id === (int)$_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Não é possível excluir o próprio usuário.']);
            exit;
        }

        $pdo->prepare("DELETE FROM messages WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM characters WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida.']);
