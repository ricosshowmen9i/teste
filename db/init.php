<?php

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir  = __DIR__;
    $dbFile = $dbDir . '/juju.db';

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        initSchema($pdo);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Database error']));
    }

    return $pdo;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        active INTEGER DEFAULT 1,
        avatar TEXT DEFAULT NULL,
        status TEXT DEFAULT 'Disponivel',
        theme TEXT DEFAULT 'green',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME DEFAULT NULL,
        force_password_change INTEGER DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS characters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT DEFAULT '',
        personality TEXT DEFAULT '',
        voice_example TEXT DEFAULT '',
        avatar TEXT DEFAULT NULL,
        bubble_color TEXT DEFAULT '#dcf8c6',
        voice_enabled INTEGER DEFAULT 0,
        voice_type TEXT DEFAULT 'feminina_adulta',
        voice_speed REAL DEFAULT 1.0,
        voice_pitch REAL DEFAULT 1.0,
        elevenlabs_id TEXT DEFAULT NULL,
        can_read_files INTEGER DEFAULT 1,
        can_generate_images INTEGER DEFAULT 0,
        long_memory INTEGER DEFAULT 1,
        context_messages INTEGER DEFAULT 20,
        auto_audio INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        character_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        file_url TEXT DEFAULT NULL,
        file_name TEXT DEFAULT NULL,
        file_type TEXT DEFAULT NULL,
        read_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (character_id) REFERENCES characters(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider TEXT DEFAULT 'openrouter',
        api_key TEXT DEFAULT '',
        base_url TEXT DEFAULT 'https://openrouter.ai/api/v1',
        model TEXT DEFAULT 'openai/gpt-3.5-turbo',
        model_mode TEXT DEFAULT 'fixed',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        requests INTEGER DEFAULT 0,
        window_start DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT DEFAULT '',
        avatar TEXT DEFAULT NULL,
        story TEXT DEFAULT '',
        interaction_mode TEXT DEFAULT 'random',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        character_id INTEGER NOT NULL,
        UNIQUE(group_id, character_id),
        FOREIGN KEY (group_id) REFERENCES groups(id),
        FOREIGN KEY (character_id) REFERENCES characters(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        sender_type TEXT NOT NULL,
        character_id INTEGER DEFAULT NULL,
        character_name TEXT DEFAULT NULL,
        content TEXT NOT NULL,
        reply_to_id INTEGER DEFAULT NULL,
        reply_to_name TEXT DEFAULT NULL,
        reply_to_snippet TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Migrations for existing tables
    $cols = array_column($pdo->query("PRAGMA table_info(ai_config)")->fetchAll(), 'name');
    if (!in_array('app_logo', $cols, true)) {
        $pdo->exec("ALTER TABLE ai_config ADD COLUMN app_logo TEXT DEFAULT NULL");
    }

    $gmCols = array_column($pdo->query("PRAGMA table_info(group_messages)")->fetchAll(), 'name');
    if (!in_array('reply_to_id', $gmCols, true)) {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN reply_to_id INTEGER DEFAULT NULL");
    }
    if (!in_array('reply_to_name', $gmCols, true)) {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN reply_to_name TEXT DEFAULT NULL");
    }
    if (!in_array('reply_to_snippet', $gmCols, true)) {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN reply_to_snippet TEXT DEFAULT NULL");
    }

    // Seed default admin
    $adminExists = $pdo->query("SELECT id FROM users WHERE email='admin@sete.app'")->fetch();
    if (!$adminExists) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, force_password_change)
            VALUES ('Admin', 'admin@sete.app', ?, 'admin', 1)");
        $stmt->execute([$hash]);
    }

    // Seed default ai_config
    $cfgExists = $pdo->query("SELECT id FROM ai_config LIMIT 1")->fetch();
    if (!$cfgExists) {
        $pdo->exec("INSERT INTO ai_config (provider, api_key, base_url, model, model_mode)
            VALUES ('openrouter', '', 'https://openrouter.ai/api/v1', 'openai/gpt-3.5-turbo', 'fixed')");
    }
}