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
    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS users (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            name TEXT NOT NULL,\n            email TEXT UNIQUE NOT NULL,\n            password TEXT NOT NULL,\n            role TEXT DEFAULT 'user',\n            active INTEGER DEFAULT 1,\n            avatar TEXT DEFAULT NULL,\n            status TEXT DEFAULT 'Disponível',\n            theme TEXT DEFAULT 'green',\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            last_login DATETIME DEFAULT NULL,\n            force_password_change INTEGER DEFAULT 0\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS characters (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            user_id INTEGER NOT NULL,\n            name TEXT NOT NULL,\n            description TEXT DEFAULT '',\n            personality TEXT DEFAULT '',\n            voice_example TEXT DEFAULT '',\n            avatar TEXT DEFAULT NULL,\n            bubble_color TEXT DEFAULT '#dcf8c6',\n            voice_enabled INTEGER DEFAULT 0,\n            voice_type TEXT DEFAULT 'feminina_adulta',\n            voice_speed REAL DEFAULT 1.0,\n            voice_pitch REAL DEFAULT 1.0,\n            elevenlabs_id TEXT DEFAULT NULL,\n            can_read_files INTEGER DEFAULT 1,\n            can_generate_images INTEGER DEFAULT 0,\n            long_memory INTEGER DEFAULT 1,\n            context_messages INTEGER DEFAULT 20,\n            auto_audio INTEGER DEFAULT 0,\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (user_id) REFERENCES users(id)\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS messages (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            user_id INTEGER NOT NULL,\n            character_id INTEGER NOT NULL,\n            role TEXT NOT NULL,\n            content TEXT NOT NULL,\n            file_url TEXT DEFAULT NULL,\n            file_name TEXT DEFAULT NULL,\n            file_type TEXT DEFAULT NULL,\n            read_at DATETIME DEFAULT NULL,\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (user_id) REFERENCES users(id),\n            FOREIGN KEY (character_id) REFERENCES characters(id)\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS ai_config (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            provider TEXT DEFAULT 'openrouter',\n            api_key TEXT DEFAULT '',\n            base_url TEXT DEFAULT 'https://openrouter.ai/api/v1',\n            model TEXT DEFAULT 'openai/gpt-3.5-turbo',\n            model_mode TEXT DEFAULT 'fixed',\n            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS rate_limits (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            session_id TEXT NOT NULL,\n            requests INTEGER DEFAULT 0,\n            window_start DATETIME DEFAULT CURRENT_TIMESTAMP\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS groups (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            user_id INTEGER NOT NULL,\n            name TEXT NOT NULL,\n            description TEXT DEFAULT '',\n            avatar TEXT DEFAULT NULL,\n            story TEXT DEFAULT '',\n            interaction_mode TEXT DEFAULT 'random',\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (user_id) REFERENCES users(id)\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS group_members (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            group_id INTEGER NOT NULL,\n            character_id INTEGER NOT NULL,\n            UNIQUE(group_id, character_id),\n            FOREIGN KEY (group_id) REFERENCES groups(id),\n            FOREIGN KEY (character_id) REFERENCES characters(id)\n        )\n    ");

    $pdo->exec("\n        CREATE TABLE IF NOT EXISTS group_messages (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            group_id INTEGER NOT NULL,\n            user_id INTEGER NOT NULL,\n            sender_type TEXT NOT NULL,\n            character_id INTEGER DEFAULT NULL,\n            character_name TEXT DEFAULT NULL,\n            content TEXT NOT NULL,\n            reply_to_id INTEGER DEFAULT NULL,\n            reply_to_name TEXT DEFAULT NULL,\n            reply_to_snippet TEXT DEFAULT NULL,\n            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (group_id) REFERENCES groups(id),\n            FOREIGN KEY (user_id) REFERENCES users(id)\n        )\n    ");

    // Migrations for existing tables
    $cols = array_column($pdo->query("PRAGMA table_info(ai_config)"").fetchAll(), 'name');
    if (!in_array('app_logo', $cols, true)) {
        $pdo->exec("ALTER TABLE ai_config ADD COLUMN app_logo TEXT DEFAULT NULL");
    }
    if (!in_array('elevenlabs_api_key', $cols, true)) {
        $pdo->exec("ALTER TABLE ai_config ADD COLUMN elevenlabs_api_key TEXT DEFAULT NULL");
    }

    $gmCols = array_column($pdo->query("PRAGMA table_info(group_messages)"").fetchAll(), 'name');
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
        $stmt = $pdo->prepare("\n            INSERT INTO users (name, email, password, role, force_password_change)\n            VALUES ('Admin', 'admin@sete.app', ?, 'admin', 1)\n        ");
        $stmt->execute([$hash]);
    }

    // Seed default ai_config
    $cfgExists = $pdo->query("SELECT id FROM ai_config LIMIT 1")->fetch();
    if (!$cfgExists) {
        $pdo->exec("\n            INSERT INTO ai_config (provider, api_key, base_url, model, model_mode)\n            VALUES ('openrouter', '', 'https://openrouter.ai/api/v1', 'openai/gpt-3.5-turbo', 'fixed')\n        ");
    }
}