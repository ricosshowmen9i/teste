<?php
/**
 * WhatsappJUJU — Chat (enviar/receber mensagens, histórico)
 */
define('WHATSAPPJUJU', true);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/chat_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireLogin();
checkRateLimit();

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? filter_input(INPUT_GET,  'action', FILTER_SANITIZE_SPECIAL_CHARS)
       ?? '';

switch ($action) {
    case 'send':
        handleSend($user);
        break;
    case 'history':
        handleHistory($user);
        break;
    case 'conversations':
        handleConversations($user);
        break;
    case 'clear':
        handleClear($user);
        break;
    case 'mark_read':
        handleMarkRead($user);
        break;
    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Envia uma mensagem e obtém resposta da IA (não-streaming).
 * Para streaming use api/stream.php.
 */
function handleSend(array $user): void {
    $convId   = (int)filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
    $content  = trim(filter_input(INPUT_POST, 'content',         FILTER_DEFAULT) ?? '');
    $msgType  = filter_input(INPUT_POST, 'message_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'text';
    $fileUrl  = filter_input(INPUT_POST, 'file_url',  FILTER_SANITIZE_URL) ?? null;
    $fileName = filter_input(INPUT_POST, 'file_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? null;

    if (!$convId || !$content) {
        jsonResponse(['error' => 'Parâmetros inválidos'], 400);
    }

    $db = getDB();

    // Verifica se a conversa pertence ao usuário
    $conv = getConversation($db, $convId, $user['id']);
    if (!$conv) {
        jsonResponse(['error' => 'Conversa não encontrada'], 404);
    }

    // Salva mensagem do usuário
    $msgId = saveMessage($db, $convId, 'user', $content, $msgType, $fileUrl, $fileName);

    // Atualiza timestamp da conversa
    $db->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")
       ->execute([':id' => $convId]);

    // Personagem e configuração de IA
    $character = getCharacter($db, $conv['character_id']);
    $aiConfig  = getAIConfig($db);

    // Histórico de mensagens
    $history = getHistory($db, $convId, $character['memory_context']);

    // Chama a IA
    $aiResponse = callAI($history, $character, $aiConfig, $user);

    if (isset($aiResponse['error'])) {
        jsonResponse(['error' => $aiResponse['error']], 502);
    }

    // Salva resposta da IA
    $aiMsgId = saveMessage($db, $convId, 'assistant', $aiResponse['content'], 'text');

    $db->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id")
       ->execute([':id' => $convId]);

    jsonResponse([
        'success'        => true,
        'user_message_id'=> $msgId,
        'ai_message'     => [
            'id'         => $aiMsgId,
            'role'       => 'assistant',
            'content'    => $aiResponse['content'],
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

/**
 * Retorna histórico de mensagens de uma conversa.
 */
function handleHistory(array $user): void {
    $convId = (int)filter_input(INPUT_GET, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
    $limit  = min((int)(filter_input(INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT) ?: 50), 200);
    $offset = (int)filter_input(INPUT_GET, 'offset', FILTER_SANITIZE_NUMBER_INT);

    if (!$convId) {
        jsonResponse(['error' => 'conversation_id obrigatório'], 400);
    }

    $db   = getDB();
    $conv = getConversation($db, $convId, $user['id']);
    if (!$conv) {
        jsonResponse(['error' => 'Conversa não encontrada'], 404);
    }

    $stmt = $db->prepare("
        SELECT id, role, content, message_type, file_url, file_name, created_at, read_at
        FROM messages
        WHERE conversation_id = :cid
        ORDER BY created_at DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':cid', $convId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $messages = array_reverse($stmt->fetchAll());

    // Marca todas como lidas
    $db->prepare("UPDATE messages SET read_at = CURRENT_TIMESTAMP
                  WHERE conversation_id = :cid AND role = 'assistant' AND read_at IS NULL")
       ->execute([':cid' => $convId]);

    jsonResponse(['success' => true, 'messages' => $messages]);
}

/**
 * Retorna lista de conversas do usuário com dados do personagem e última mensagem.
 */
function handleConversations(array $user): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            c.id,
            c.character_id,
            c.pinned,
            c.muted,
            c.updated_at,
            ch.name        AS character_name,
            ch.avatar      AS character_avatar,
            ch.description AS character_description,
            ch.bubble_color,
            ch.voice_type,
            ch.voice_speed,
            ch.voice_pitch,
            ch.voice_enabled,
            ch.elevenlabs_voice_id,
            (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT message_type FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_type,
            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_at,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND role = 'assistant' AND read_at IS NULL) AS unread_count
        FROM conversations c
        JOIN characters ch ON ch.id = c.character_id
        WHERE c.user_id = :uid
        ORDER BY c.pinned DESC, c.updated_at DESC
    ");
    $stmt->execute([':uid' => $user['id']]);
    $convs = $stmt->fetchAll();

    jsonResponse(['success' => true, 'conversations' => $convs]);
}

/**
 * Limpa o histórico de uma conversa.
 */
function handleClear(array $user): void {
    $convId = (int)filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
    if (!$convId) {
        jsonResponse(['error' => 'conversation_id obrigatório'], 400);
    }

    $db   = getDB();
    $conv = getConversation($db, $convId, $user['id']);
    if (!$conv) {
        jsonResponse(['error' => 'Conversa não encontrada'], 404);
    }

    $db->prepare("DELETE FROM messages WHERE conversation_id = :cid")
       ->execute([':cid' => $convId]);

    jsonResponse(['success' => true]);
}

/**
 * Marca mensagens como lidas.
 */
function handleMarkRead(array $user): void {
    $convId = (int)filter_input(INPUT_POST, 'conversation_id', FILTER_SANITIZE_NUMBER_INT);
    if (!$convId) {
        jsonResponse(['error' => 'conversation_id obrigatório'], 400);
    }

    $db   = getDB();
    $conv = getConversation($db, $convId, $user['id']);
    if (!$conv) {
        jsonResponse(['error' => 'Conversa não encontrada'], 404);
    }

    $db->prepare("UPDATE messages SET read_at = CURRENT_TIMESTAMP
                  WHERE conversation_id = :cid AND role = 'assistant' AND read_at IS NULL")
       ->execute([':cid' => $convId]);

    jsonResponse(['success' => true]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Fim de chat.php — funções auxiliares estão em chat_helpers.php
