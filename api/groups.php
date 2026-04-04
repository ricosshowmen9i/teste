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

// ─── GET: list groups ─────────────────────────────────────────────────────────
if ($method === 'GET' && $action === '') {
    $stmt = $pdo->prepare("
        SELECT g.*,
               COUNT(DISTINCT gm.character_id) AS member_count,
               (SELECT content FROM group_messages
                WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) AS last_message,
               (SELECT created_at FROM group_messages
                WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) AS last_message_time
        FROM groups g
        LEFT JOIN group_members gm ON gm.group_id = g.id
        WHERE g.user_id = ?
        GROUP BY g.id
        ORDER BY last_message_time DESC, g.created_at DESC
    ");
    $stmt->execute([$userId]);
    $groups = $stmt->fetchAll();

    foreach ($groups as &$group) {
        $mStmt = $pdo->prepare("
            SELECT c.id, c.name, c.avatar
            FROM group_members gm
            JOIN characters c ON c.id = gm.character_id
            WHERE gm.group_id = ?
        ");
        $mStmt->execute([$group['id']]);
        $group['members'] = $mStmt->fetchAll();
    }
    unset($group);

    echo json_encode(['groups' => $groups]);
    exit;
}

// ─── GET: history ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'history') {
    $groupId = (int)($_GET['group_id'] ?? 0);
    if (!$groupId) {
        http_response_code(400);
        echo json_encode(['error' => 'group_id é obrigatório.']);
        exit;
    }

    $grp = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
    $grp->execute([$groupId, $userId]);
    if (!$grp->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Grupo não encontrado.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM group_messages
        WHERE group_id = ?
        ORDER BY created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$groupId]);
    echo json_encode(['messages' => $stmt->fetchAll()]);
    exit;
}

// ─── POST actions ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }

    // ── create ────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $name    = trim($_POST['name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $story   = trim($_POST['story'] ?? '');
        $mode    = $_POST['interaction_mode'] ?? 'random';
        $members = json_decode($_POST['member_ids'] ?? '[]', true);

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome é obrigatório.']);
            exit;
        }

        $allowedModes = ['random', 'story', 'topic'];
        if (!in_array($mode, $allowedModes, true)) $mode = 'random';

        $stmt = $pdo->prepare("
            INSERT INTO groups (user_id, name, description, story, interaction_mode)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $name, $desc, $story, $mode]);
        $groupId = (int)$pdo->lastInsertId();

        if (is_array($members)) {
            foreach ($members as $charId) {
                $charId = (int)$charId;
                if (!$charId) continue;
                $own = $pdo->prepare("SELECT id FROM characters WHERE id = ? AND user_id = ?");
                $own->execute([$charId, $userId]);
                if (!$own->fetch()) continue;
                $pdo->prepare("INSERT OR IGNORE INTO group_members (group_id, character_id) VALUES (?, ?)")
                    ->execute([$groupId, $charId]);
            }
        }

        echo json_encode(['success' => true, 'id' => $groupId]);
        exit;
    }

    // ── update ────────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $groupId = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $story   = trim($_POST['story'] ?? '');
        $mode    = $_POST['interaction_mode'] ?? 'random';

        if (!$groupId || !$name) {
            http_response_code(400);
            echo json_encode(['error' => 'id e nome são obrigatórios.']);
            exit;
        }

        $allowedModes = ['random', 'story', 'topic'];
        if (!in_array($mode, $allowedModes, true)) $mode = 'random';

        $stmt = $pdo->prepare("
            UPDATE groups SET name = ?, description = ?, story = ?, interaction_mode = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$name, $desc, $story, $mode, $groupId, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Grupo não encontrado.']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // ── delete ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $groupId = (int)($_POST['id'] ?? 0);
        if (!$groupId) {
            http_response_code(400);
            echo json_encode(['error' => 'id é obrigatório.']);
            exit;
        }

        $grp = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
        $grp->execute([$groupId, $userId]);
        if (!$grp->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Grupo não encontrado.']);
            exit;
        }

        $pdo->prepare("DELETE FROM group_messages WHERE group_id = ?")->execute([$groupId]);
        $pdo->prepare("DELETE FROM group_members WHERE group_id = ?")->execute([$groupId]);
        $pdo->prepare("DELETE FROM groups WHERE id = ? AND user_id = ?")->execute([$groupId, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ── add_member ────────────────────────────────────────────────────────────
    if ($action === 'add_member') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $charId  = (int)($_POST['character_id'] ?? 0);

        if (!$groupId || !$charId) {
            http_response_code(400);
            echo json_encode(['error' => 'group_id e character_id são obrigatórios.']);
            exit;
        }

        $grp = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
        $grp->execute([$groupId, $userId]);
        if (!$grp->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Grupo não encontrado.']);
            exit;
        }

        $own = $pdo->prepare("SELECT id FROM characters WHERE id = ? AND user_id = ?");
        $own->execute([$charId, $userId]);
        if (!$own->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Personagem não encontrado.']);
            exit;
        }

        $pdo->prepare("INSERT OR IGNORE INTO group_members (group_id, character_id) VALUES (?, ?)")
            ->execute([$groupId, $charId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ── remove_member ─────────────────────────────────────────────────────────
    if ($action === 'remove_member') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $charId  = (int)($_POST['character_id'] ?? 0);

        if (!$groupId || !$charId) {
            http_response_code(400);
            echo json_encode(['error' => 'group_id e character_id são obrigatórios.']);
            exit;
        }

        $grp = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
        $grp->execute([$groupId, $userId]);
        if (!$grp->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Grupo não encontrado.']);
            exit;
        }

        $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND character_id = ?")
            ->execute([$groupId, $charId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ── clear ─────────────────────────────────────────────────────────────────
    if ($action === 'clear') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        if (!$groupId) {
            http_response_code(400);
            echo json_encode(['error' => 'group_id é obrigatório.']);
            exit;
        }

        $grp = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
        $grp->execute([$groupId, $userId]);
        if (!$grp->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Grupo não encontrado.']);
            exit;
        }

        $pdo->prepare("DELETE FROM group_messages WHERE group_id = ?")->execute([$groupId]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Método não permitido.']);
