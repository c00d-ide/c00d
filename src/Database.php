<?php
/**
 * c00d IDE - SQLite Database Handler
 * Self-contained database for preferences, history, and state
 */

namespace C00d;

class Database {
    private static ?Database $instance = null;
    private \PDO $db;
    private string $dbPath;

    private function __construct(string $dataDir) {
        $this->dbPath = $dataDir . '/c00d.db';
        $this->connect();
        $this->migrate();
    }

    public static function getInstance(string $dataDir = null): Database {
        if (self::$instance === null) {
            if ($dataDir === null) {
                $dataDir = dirname(__DIR__) . '/data';
            }
            self::$instance = new self($dataDir);
        }
        return self::$instance;
    }

    private function connect(): void {
        $this->db = new \PDO('sqlite:' . $this->dbPath);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('PRAGMA journal_mode = WAL');
    }

    private function migrate(): void {
        $version = $this->getVersion();

        if ($version < 1) {
            $this->db->exec("
                -- Settings table
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                -- Open tabs / editor state
                CREATE TABLE IF NOT EXISTS editor_state (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_path TEXT NOT NULL,
                    cursor_line INTEGER DEFAULT 1,
                    cursor_column INTEGER DEFAULT 1,
                    scroll_top INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 0,
                    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                -- Command history
                CREATE TABLE IF NOT EXISTS command_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    command TEXT NOT NULL,
                    working_dir TEXT,
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                -- AI chat history
                CREATE TABLE IF NOT EXISTS ai_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    role TEXT NOT NULL,
                    content TEXT NOT NULL,
                    context_file TEXT,
                    tokens_used INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                -- AI usage tracking (for rate limiting free tier)
                CREATE TABLE IF NOT EXISTS ai_usage (
                    date TEXT PRIMARY KEY,
                    request_count INTEGER DEFAULT 0,
                    tokens_used INTEGER DEFAULT 0
                );

                -- File bookmarks
                CREATE TABLE IF NOT EXISTS bookmarks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_path TEXT NOT NULL,
                    line_number INTEGER,
                    label TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                -- Recent files
                CREATE TABLE IF NOT EXISTS recent_files (
                    file_path TEXT PRIMARY KEY,
                    accessed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            $this->setVersion(1);
        }
    }

    private function getVersion(): int {
        try {
            $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'db_version'");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? (int)$row['value'] : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function setVersion(int $version): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('db_version', ?)");
        $stmt->execute([$version]);
    }

    // Settings
    public function getSetting(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? json_decode($row['value'], true) ?? $row['value'] : $default;
    }

    public function setSetting(string $key, $value): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$key, is_array($value) ? json_encode($value) : $value]);
    }

    // Editor state
    public function getOpenTabs(): array {
        $stmt = $this->db->query("SELECT * FROM editor_state ORDER BY opened_at");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveTab(string $filePath, int $line = 1, int $col = 1, bool $isActive = false): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO editor_state (file_path, cursor_line, cursor_column, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$filePath, $line, $col, $isActive ? 1 : 0]);
    }

    public function closeTab(string $filePath): void {
        $stmt = $this->db->prepare("DELETE FROM editor_state WHERE file_path = ?");
        $stmt->execute([$filePath]);
    }

    public function clearTabs(): void {
        $this->db->exec("DELETE FROM editor_state");
    }

    // Command history
    public function addCommand(string $command, string $workingDir = ''): void {
        $stmt = $this->db->prepare("INSERT INTO command_history (command, working_dir) VALUES (?, ?)");
        $stmt->execute([$command, $workingDir]);

        // Keep only last 1000 commands
        $this->db->exec("DELETE FROM command_history WHERE id NOT IN (SELECT id FROM command_history ORDER BY id DESC LIMIT 1000)");
    }

    public function getCommandHistory(int $limit = 100): array {
        $stmt = $this->db->prepare("SELECT command FROM command_history ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'command');
    }

    // AI history
    public function addAiMessage(string $role, string $content, string $contextFile = null, int $tokens = 0): void {
        $stmt = $this->db->prepare("INSERT INTO ai_history (role, content, context_file, tokens_used) VALUES (?, ?, ?, ?)");
        $stmt->execute([$role, $content, $contextFile, $tokens]);
    }

    public function getAiHistory(int $limit = 50): array {
        $stmt = $this->db->prepare("SELECT * FROM ai_history ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function clearAiHistory(): void {
        $this->db->exec("DELETE FROM ai_history");
    }

    // AI usage tracking
    public function incrementAiUsage(int $tokens = 0): int {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("INSERT INTO ai_usage (date, request_count, tokens_used) VALUES (?, 1, ?)
            ON CONFLICT(date) DO UPDATE SET request_count = request_count + 1, tokens_used = tokens_used + ?");
        $stmt->execute([$today, $tokens, $tokens]);

        $stmt = $this->db->prepare("SELECT request_count FROM ai_usage WHERE date = ?");
        $stmt->execute([$today]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['request_count'];
    }

    public function getTodayAiUsage(): array {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("SELECT * FROM ai_usage WHERE date = ?");
        $stmt->execute([$today]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['request_count' => 0, 'tokens_used' => 0];
    }

    // Recent files
    public function addRecentFile(string $filePath): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO recent_files (file_path, accessed_at) VALUES (?, CURRENT_TIMESTAMP)");
        $stmt->execute([$filePath]);

        // Keep only last 50
        $this->db->exec("DELETE FROM recent_files WHERE file_path NOT IN (SELECT file_path FROM recent_files ORDER BY accessed_at DESC LIMIT 50)");
    }

    public function getRecentFiles(int $limit = 20): array {
        $stmt = $this->db->prepare("SELECT file_path FROM recent_files ORDER BY accessed_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'file_path');
    }

    // Bookmarks
    public function addBookmark(string $filePath, int $line, string $label = ''): void {
        $stmt = $this->db->prepare("INSERT INTO bookmarks (file_path, line_number, label) VALUES (?, ?, ?)");
        $stmt->execute([$filePath, $line, $label]);
    }

    public function getBookmarks(): array {
        return $this->db->query("SELECT * FROM bookmarks ORDER BY created_at DESC")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function removeBookmark(int $id): void {
        $stmt = $this->db->prepare("DELETE FROM bookmarks WHERE id = ?");
        $stmt->execute([$id]);
    }
}
