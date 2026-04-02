<?php
/**
 * WhatsappJUJU — Configurações Globais
 */

// Impede acesso direto
if (!defined('WHATSAPPJUJU')) {
    define('WHATSAPPJUJU', true);
}

// Caminho base da aplicação (usando __DIR__ do config.php que fica em sete/)
define('BASE_PATH',   __DIR__);
define('DB_PATH',     BASE_PATH . '/db/sete.db');
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// URL base (detecta automaticamente subpasta)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl  = rtrim($protocol . '://' . $host . $script, '/');
define('BASE_URL', $baseUrl);

// Limites de upload
define('MAX_IMAGE_SIZE',  5 * 1024 * 1024);  // 5 MB
define('MAX_DOC_SIZE',   10 * 1024 * 1024);  // 10 MB
define('MAX_CODE_SIZE',   2 * 1024 * 1024);  // 2 MB

// Tipos permitidos
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_EXTS',    ['pdf', 'txt', 'docx', 'csv', 'json', 'md']);
define('ALLOWED_CODE_EXTS',   ['js', 'php', 'py', 'html', 'css']);

// Rate-limiting
define('RATE_LIMIT', 30); // requisições por minuto

// Modelos gratuitos do OpenRouter
define('FREE_MODELS', [
    'nvidia/nemotron-nano-12b-v2-vl:free',
    'nvidia/nemotron-3-super-120b-a12b:free',
    'stepfun/step-3.5-flash:free',
    'qwen/qwen3.6-plus-preview:free',
    'arcee-ai/trinity-large-preview:free',
    'z-ai/glm-4.5-air:free',
    'nvidia/nemotron-3-nano-30b-a3b:free',
    'arcee-ai/trinity-mini:free',
    'nvidia/nemotron-nano-9b-v2:free',
    'minimax/minimax-m2.5:free',
    'qwen/qwen3-coder:free',
    'qwen/qwen3.6-plus:free',
    'google/gemma-3-27b-it:free',
    'google/gemma-3-4b-it:free',
    'nvidia/llama-nemotron-embed-vl-1b-v2:free',
]);

// Sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Erros (desative em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Conexão PDO com SQLite — cria banco e tabelas na primeira execução.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');

    createTables($pdo);
    return $pdo;
}

/**
 * Cria as tabelas e dados iniciais caso não existam.
 */
function createTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            avatar TEXT DEFAULT NULL,
            status TEXT DEFAULT 'online',
            role TEXT DEFAULT 'user',
            theme TEXT DEFAULT 'verde',
            force_password_change INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS characters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            avatar TEXT DEFAULT NULL,
            description TEXT,
            personality TEXT,
            voice_example TEXT,
            voice_type TEXT DEFAULT 'feminina_adulta',
            voice_speed REAL DEFAULT 1.0,
            voice_pitch REAL DEFAULT 1.0,
            bubble_color TEXT DEFAULT '#dcf8c6',
            can_generate_images INTEGER DEFAULT 0,
            can_read_files INTEGER DEFAULT 1,
            memory_context INTEGER DEFAULT 20,
            auto_audio INTEGER DEFAULT 0,
            long_memory INTEGER DEFAULT 0,
            elevenlabs_voice_id TEXT DEFAULT NULL,
            voice_enabled INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            character_id INTEGER NOT NULL,
            pinned INTEGER DEFAULT 0,
            muted INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            message_type TEXT DEFAULT 'text',
            file_url TEXT DEFAULT NULL,
            file_name TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS ai_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT DEFAULT 'openrouter',
            api_key TEXT DEFAULT '',
            base_url TEXT DEFAULT 'https://openrouter.ai/api/v1',
            model TEXT DEFAULT 'mistralai/mistral-7b-instruct:free',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        INSERT OR IGNORE INTO ai_config (id) VALUES (1);
    ");

    // Migrations: adiciona colunas novas sem quebrar banco existente
    $migrations = [
        "ALTER TABLE ai_config ADD COLUMN model_mode TEXT DEFAULT 'random'",
        "ALTER TABLE ai_config ADD COLUMN free_models TEXT DEFAULT ''",
    ];
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
        } catch (\Exception $e) {
            // Ignora erro "duplicate column name" — coluna já existe
            if (strpos($e->getMessage(), 'duplicate column name') === false) {
                error_log('Migration warning: ' . $e->getMessage());
            }
        }
    }

    // Cria admin padrão se não existir
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@sete.app'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $ins  = $pdo->prepare("INSERT INTO users (name, email, password, role, force_password_change)
                                VALUES ('Admin', 'admin@sete.app', :pw, 'admin', 1)");
        $ins->execute([':pw' => $hash]);
    }
}

/**
 * Verifica se o usuário está logado; se não, retorna JSON de erro ou redireciona.
 */
function requireLogin(bool $jsonError = true): array {
    if (empty($_SESSION['user_id'])) {
        if ($jsonError) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'role'  => $_SESSION['user_role'] ?? 'user',
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}

/**
 * Verifica se o usuário é admin.
 */
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
}

/**
 * Rate-limiting simples por sessão.
 */
function checkRateLimit(): void {
    $now    = time();
    $window = 60; // segundos

    if (empty($_SESSION['rl_count'])) {
        $_SESSION['rl_count'] = 0;
        $_SESSION['rl_start'] = $now;
    }

    if ($now - $_SESSION['rl_start'] > $window) {
        $_SESSION['rl_count'] = 0;
        $_SESSION['rl_start'] = $now;
    }

    $_SESSION['rl_count']++;

    if ($_SESSION['rl_count'] > RATE_LIMIT) {
        http_response_code(429);
        echo json_encode(['error' => 'Muitas requisições. Aguarde um momento.']);
        exit;
    }
}

/**
 * Retorna JSON e encerra o script.
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitiza string simples.
 */
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}
