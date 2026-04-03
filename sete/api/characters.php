<?php
/**
 * WhatsappJUJU — CRUD de Personagens/Contatos
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireLogin();

// Suporta JSON body (fetch/axios), form-encoded ($.post) e query string ($.get)
$jsonInput = null;
$rawBody   = file_get_contents('php://input');
if ($rawBody) {
    $jsonInput = json_decode($rawBody, true);
}

$action = '';
if ($jsonInput && isset($jsonInput['action'])) {
    $action = $jsonInput['action'];
} else {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS)
           ?? filter_input(INPUT_GET,  'action', FILTER_SANITIZE_SPECIAL_CHARS)
           ?? '';
}

switch ($action) {
    case 'list':
        handleList($user);
        break;
    case 'get':
        handleGet($user);
        break;
    case 'create':
        handleCreate($user, $jsonInput);
        break;
    case 'update':
        handleUpdate($user, $jsonInput);
        break;
    case 'delete':
        handleDelete($user, $jsonInput);
        break;
    case 'open_conversation':
        handleOpenConversation($user, $jsonInput);
        break;
    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}

// ──────────────────────────────────────────────────────────────────────────────

function handleList(array $user): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            ch.*,
            (SELECT id FROM conversations WHERE user_id = :uid AND character_id = ch.id LIMIT 1) AS conversation_id
        FROM characters ch
        WHERE ch.user_id = :uid2
        ORDER BY ch.name ASC
    ");
    $stmt->execute([':uid' => $user['id'], ':uid2' => $user['id']]);
    $chars = $stmt->fetchAll();

    jsonResponse(['success' => true, 'characters' => $chars]);
}

function handleGet(array $user): void {
    $id = (int)filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    if (!$id) {
        jsonResponse(['error' => 'ID obrigatório'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM characters WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $id, ':uid' => $user['id']]);
    $char = $stmt->fetch();

    if (!$char) {
        jsonResponse(['error' => 'Personagem não encontrado'], 404);
    }

    jsonResponse(['success' => true, 'character' => $char]);
}

function handleCreate(array $user, ?array $jsonInput = null): void {
    $data = extractCharacterData($jsonInput);

    if (!$data[':name']) {
        jsonResponse(['error' => 'Nome é obrigatório'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO characters
            (user_id, name, avatar, description, personality, voice_example,
             voice_type, voice_speed, voice_pitch, bubble_color,
             can_generate_images, can_read_files, memory_context,
             auto_audio, long_memory, elevenlabs_voice_id, voice_enabled)
        VALUES
            (:uid, :name, :avatar, :desc, :personality, :voice_example,
             :voice_type, :voice_speed, :voice_pitch, :bubble_color,
             :can_gen, :can_read, :memory,
             :auto_audio, :long_memory, :elevenlabs, :voice_enabled)
    ");
    $stmt->execute(array_merge([':uid' => $user['id']], $data));

    $charId = (int)$db->lastInsertId();

    // Cria conversa automaticamente
    $conv = $db->prepare("INSERT INTO conversations (user_id, character_id) VALUES (:uid, :cid)");
    $conv->execute([':uid' => $user['id'], ':cid' => $charId]);
    $convId = (int)$db->lastInsertId();

    jsonResponse(['success' => true, 'character_id' => $charId, 'conversation_id' => $convId]);
}

function handleUpdate(array $user, ?array $jsonInput = null): void {
    $id = (int)(($jsonInput['id'] ?? null) ?: filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID obrigatório'], 400);
    }

    $db   = getDB();
    $chk  = $db->prepare("SELECT id FROM characters WHERE id = :id AND user_id = :uid");
    $chk->execute([':id' => $id, ':uid' => $user['id']]);
    if (!$chk->fetch()) {
        jsonResponse(['error' => 'Personagem não encontrado'], 404);
    }

    $data = extractCharacterData($jsonInput);

    $stmt = $db->prepare("
        UPDATE characters SET
            name = :name, avatar = :avatar, description = :desc,
            personality = :personality, voice_example = :voice_example,
            voice_type = :voice_type, voice_speed = :voice_speed,
            voice_pitch = :voice_pitch, bubble_color = :bubble_color,
            can_generate_images = :can_gen, can_read_files = :can_read,
            memory_context = :memory, auto_audio = :auto_audio,
            long_memory = :long_memory, elevenlabs_voice_id = :elevenlabs,
            voice_enabled = :voice_enabled
        WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute(array_merge($data, [':id' => $id, ':uid' => $user['id']]));

    jsonResponse(['success' => true]);
}

function handleDelete(array $user, ?array $jsonInput = null): void {
    $id = (int)(($jsonInput['id'] ?? null) ?: filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT));
    if (!$id) {
        jsonResponse(['error' => 'ID obrigatório'], 400);
    }

    $db  = getDB();
    $chk = $db->prepare("SELECT id FROM characters WHERE id = :id AND user_id = :uid");
    $chk->execute([':id' => $id, ':uid' => $user['id']]);
    if (!$chk->fetch()) {
        jsonResponse(['error' => 'Personagem não encontrado'], 404);
    }

    $db->prepare("DELETE FROM characters WHERE id = :id AND user_id = :uid")
       ->execute([':id' => $id, ':uid' => $user['id']]);

    jsonResponse(['success' => true]);
}

function handleOpenConversation(array $user, ?array $jsonInput = null): void {
    $charId = (int)(($jsonInput['character_id'] ?? null) ?: filter_input(INPUT_POST, 'character_id', FILTER_SANITIZE_NUMBER_INT));
    if (!$charId) {
        jsonResponse(['error' => 'character_id obrigatório'], 400);
    }

    $db  = getDB();
    $chk = $db->prepare("SELECT id FROM characters WHERE id = :id AND user_id = :uid");
    $chk->execute([':id' => $charId, ':uid' => $user['id']]);
    if (!$chk->fetch()) {
        jsonResponse(['error' => 'Personagem não encontrado'], 404);
    }

    // Verifica se já existe conversa
    $stmt = $db->prepare("SELECT id FROM conversations WHERE user_id = :uid AND character_id = :cid LIMIT 1");
    $stmt->execute([':uid' => $user['id'], ':cid' => $charId]);
    $conv = $stmt->fetch();

    if ($conv) {
        jsonResponse(['success' => true, 'conversation_id' => $conv['id']]);
    }

    // Cria nova conversa
    $ins = $db->prepare("INSERT INTO conversations (user_id, character_id) VALUES (:uid, :cid)");
    $ins->execute([':uid' => $user['id'], ':cid' => $charId]);
    $convId = (int)$db->lastInsertId();

    jsonResponse(['success' => true, 'conversation_id' => $convId]);
}

// ──────────────────────────────────────────────────────────────────────────────

function extractCharacterData(?array $jsonInput = null): array {
    // Usa JSON body se disponível, senão fallback para $_POST
    $src = (!empty($jsonInput)) ? $jsonInput : $_POST;

    $str = function(string $key, string $default = '') use ($src): string {
        return trim((string)($src[$key] ?? $default));
    };

    $bool = function(string $key) use ($src): int {
        $v = $src[$key] ?? false;
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_numeric($v)) return (int)$v ? 1 : 0;
        $lower = strtolower((string)$v);
        return in_array($lower, ['true', '1', 'yes', 'on']) ? 1 : 0;
    };

    $float = function(string $key, float $default) use ($src): float {
        $v = $src[$key] ?? null;
        return ($v !== null && $v !== '') ? (float)$v : $default;
    };

    $int = function(string $key, int $default) use ($src): int {
        $v = $src[$key] ?? null;
        return ($v !== null && $v !== '') ? (int)$v : $default;
    };

    // Avatar: só salva se foi enviado um novo valor
    $avatar = $str('avatar');
    if (!$avatar) {
        $avatar = null;
    }

    return [
        ':name'          => $str('name'),
        ':avatar'        => $avatar,
        ':desc'          => $str('description'),
        ':personality'   => $str('personality'),
        ':voice_example' => $str('voice_example'),
        ':voice_type'    => $str('voice_type') ?: 'feminina_adulta',
        ':voice_speed'   => $float('voice_speed', 1.0),
        ':voice_pitch'   => $float('voice_pitch', 1.0),
        ':bubble_color'  => $str('bubble_color') ?: '#dcf8c6',
        ':can_gen'       => $bool('can_generate_images'),
        ':can_read'      => $bool('can_read_files'),
        ':memory'        => $int('memory_context', 20),
        ':auto_audio'    => $bool('auto_audio'),
        ':long_memory'   => $bool('long_memory'),
        ':elevenlabs'    => $str('elevenlabs_voice_id') ?: null,
        ':voice_enabled' => $bool('voice_enabled'),
    ];
}
