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
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo    = getDB();

if ($method === 'GET' && !$action) {
    // List characters for current user with last message + unread count
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT content FROM messages WHERE character_id = c.id AND user_id = ? ORDER BY created_at DESC LIMIT 1) AS last_message,
               (SELECT created_at FROM messages WHERE character_id = c.id AND user_id = ? ORDER BY created_at DESC LIMIT 1) AS last_message_time,
               (SELECT COUNT(*) FROM messages WHERE character_id = c.id AND user_id = ? AND role = 'assistant' AND read_at IS NULL) AS unread_count
        FROM characters c
        WHERE c.user_id = ?
        ORDER BY last_message_time DESC, c.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $characters = $stmt->fetchAll();

    echo json_encode(['characters' => $characters]);
    exit;
}

if ($method === 'POST') {
    // Validate CSRF
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }

    if ($action === 'create') {
        $name       = trim($_POST['name'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $personality = trim($_POST['personality'] ?? '');
        $voiceExample = trim($_POST['voice_example'] ?? '');
        $bubbleColor = trim($_POST['bubble_color'] ?? '#dcf8c6');
        $voiceEnabled = (int)($_POST['voice_enabled'] ?? 0);
        $voiceType   = trim($_POST['voice_type'] ?? 'feminina_adulta');
        $voiceSpeed  = (float)($_POST['voice_speed'] ?? 1.0);
        $voicePitch  = (float)($_POST['voice_pitch'] ?? 1.0);
        $elevenLabsId = trim($_POST['elevenlabs_id'] ?? '');
        $canReadFiles = (int)($_POST['can_read_files'] ?? 1);
        $canGenImages = (int)($_POST['can_generate_images'] ?? 0);
        $longMemory  = (int)($_POST['long_memory'] ?? 1);
        $ctxMessages = (int)($_POST['context_messages'] ?? 20);
        $autoAudio   = (int)($_POST['auto_audio'] ?? 0);
        $avatar      = trim($_POST['avatar'] ?? '');

        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome do personagem é obrigatório.']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO characters
                (user_id, name, description, personality, voice_example, avatar, bubble_color,
                 voice_enabled, voice_type, voice_speed, voice_pitch, elevenlabs_id,
                 can_read_files, can_generate_images, long_memory, context_messages, auto_audio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $name, $desc, $personality, $voiceExample, $avatar ?: null, $bubbleColor,
            $voiceEnabled, $voiceType, $voiceSpeed, $voicePitch, $elevenLabsId ?: null,
            $canReadFiles, $canGenImages, $longMemory, $ctxMessages, $autoAudio,
        ]);

        $id   = $pdo->lastInsertId();
        $char = $pdo->prepare("SELECT * FROM characters WHERE id = ?")->execute([$id]);
        $char = $pdo->query("SELECT * FROM characters WHERE id = $id")->fetch();

        echo json_encode(['success' => true, 'character' => $char]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        // Verify ownership
        $existing = $pdo->prepare("SELECT * FROM characters WHERE id = ? AND user_id = ?");
        $existing->execute([$id, $userId]);
        $char = $existing->fetch();
        if (!$char) {
            http_response_code(404);
            echo json_encode(['error' => 'Personagem não encontrado.']);
            exit;
        }

        $fields = [
            'name'               => trim($_POST['name'] ?? $char['name']),
            'description'        => trim($_POST['description'] ?? $char['description']),
            'personality'        => trim($_POST['personality'] ?? $char['personality']),
            'voice_example'      => trim($_POST['voice_example'] ?? $char['voice_example']),
            'bubble_color'       => trim($_POST['bubble_color'] ?? $char['bubble_color']),
            'voice_enabled'      => (int)($_POST['voice_enabled'] ?? $char['voice_enabled']),
            'voice_type'         => trim($_POST['voice_type'] ?? $char['voice_type']),
            'voice_speed'        => (float)($_POST['voice_speed'] ?? $char['voice_speed']),
            'voice_pitch'        => (float)($_POST['voice_pitch'] ?? $char['voice_pitch']),
            'elevenlabs_id'      => trim($_POST['elevenlabs_id'] ?? $char['elevenlabs_id']),
            'can_read_files'     => (int)($_POST['can_read_files'] ?? $char['can_read_files']),
            'can_generate_images'=> (int)($_POST['can_generate_images'] ?? $char['can_generate_images']),
            'long_memory'        => (int)($_POST['long_memory'] ?? $char['long_memory']),
            'context_messages'   => (int)($_POST['context_messages'] ?? $char['context_messages']),
            'auto_audio'         => (int)($_POST['auto_audio'] ?? $char['auto_audio']),
            'avatar'             => trim($_POST['avatar'] ?? $char['avatar']),
        ];

        if (!$fields['name']) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome é obrigatório.']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE characters SET
                name = ?, description = ?, personality = ?, voice_example = ?,
                bubble_color = ?, voice_enabled = ?, voice_type = ?, voice_speed = ?,
                voice_pitch = ?, elevenlabs_id = ?, can_read_files = ?,
                can_generate_images = ?, long_memory = ?, context_messages = ?,
                auto_audio = ?, avatar = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $fields['name'], $fields['description'], $fields['personality'],
            $fields['voice_example'], $fields['bubble_color'], $fields['voice_enabled'],
            $fields['voice_type'], $fields['voice_speed'], $fields['voice_pitch'],
            $fields['elevenlabs_id'] ?: null, $fields['can_read_files'],
            $fields['can_generate_images'], $fields['long_memory'],
            $fields['context_messages'], $fields['auto_audio'],
            $fields['avatar'] ?: null, $id, $userId,
        ]);

        $updated = $pdo->prepare("SELECT * FROM characters WHERE id = ?")->execute([$id]);
        $updated = $pdo->query("SELECT * FROM characters WHERE id = $id")->fetch();

        echo json_encode(['success' => true, 'character' => $updated]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM characters WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Personagem não encontrado.']);
            exit;
        }

        // Also delete messages
        $pdo->prepare("DELETE FROM messages WHERE character_id = ? AND user_id = ?")
            ->execute([$id, $userId]);

        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida.']);
