package db

// RunMigrations creates all necessary database tables
func RunMigrations() {
	DB.Exec(`
		CREATE TABLE IF NOT EXISTS settings (
			key TEXT PRIMARY KEY,
			value TEXT
		);
		CREATE TABLE IF NOT EXISTS command_history (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			command TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		CREATE TABLE IF NOT EXISTS ai_history (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			role TEXT,
			content TEXT,
			file_context TEXT,
			tokens INTEGER DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		CREATE TABLE IF NOT EXISTS ai_usage (
			date TEXT PRIMARY KEY,
			request_count INTEGER DEFAULT 0,
			token_count INTEGER DEFAULT 0
		);
		CREATE TABLE IF NOT EXISTS sessions (
			id TEXT PRIMARY KEY,
			expires_at DATETIME NOT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);
		CREATE TABLE IF NOT EXISTS ip_logs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ip_address TEXT NOT NULL,
			endpoint TEXT,
			method TEXT,
			user_agent TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP
		);
		CREATE INDEX IF NOT EXISTS idx_ip_logs_ip ON ip_logs(ip_address);
		CREATE INDEX IF NOT EXISTS idx_ip_logs_created ON ip_logs(created_at);
	`)
}
