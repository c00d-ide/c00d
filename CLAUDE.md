# c00d System Documentation

This document provides context for LLM agents working on the c00d codebase.

## Overview

c00d is a self-hosted web IDE available in two versions:
- **Go Binary** (`/go/`) - Single binary, recommended for new installs
- **PHP Version** (`/opensource/`) - Traditional web hosting, requires PHP 8.0+

The main website (`/public/`) is a separate PHP application that serves the marketing site, downloads, pro licensing, etc.

---

## Go Version (`/go/`)

### Architecture

```
go/
├── main.go                    # Entry point (~115 lines)
├── internal/
│   ├── config/config.go       # Config struct, Load(), ApplyDefaults(), ShouldLogIPs()
│   ├── db/
│   │   ├── db.go              # Init(), DB global variable
│   │   ├── migrations.go      # Schema: settings, command_history, ai_history, ai_usage, sessions, ip_logs
│   │   ├── sessions.go        # CreateSession, ValidateSession, DeleteSession, CleanupExpiredSessions
│   │   └── iplog.go           # LogIP, GetClientIP, GetIPLogs, GetUniqueIPs
│   ├── auth/
│   │   ├── middleware.go      # Middleware() - checks session validity
│   │   ├── session.go         # StartCleanupRoutine() - hourly expired session cleanup
│   │   └── logging.go         # WithLogging() - IP logging middleware
│   ├── handlers/
│   │   ├── auth.go            # GET/POST/DELETE /api/auth
│   │   ├── files.go           # GET /api/files - directory listing
│   │   ├── file.go            # GET/POST/PUT/DELETE/PATCH /api/file
│   │   ├── terminal.go        # POST /api/terminal - command execution
│   │   ├── ai.go              # POST /api/ai - AI chat
│   │   ├── config.go          # GET /api/config - editor/AI settings
│   │   ├── git.go             # POST /api/git - git operations
│   │   ├── search.go          # POST /api/search - file content search
│   │   ├── iplogs.go          # GET /api/iplogs - view IP logs
│   │   └── frontend.go        # Serves embedded frontend files
│   ├── ai/
│   │   ├── provider.go        # Provider interface
│   │   ├── c00d.go            # c00d API provider
│   │   ├── anthropic.go       # Anthropic API
│   │   ├── openai.go          # OpenAI API
│   │   └── ollama.go          # Ollama local API
│   ├── git/git.go             # Git command wrapper functions
│   └── security/path.go       # ValidatePath, ValidateFullPath helpers
```

### Configuration (config.yaml)

```yaml
port: 3000
base_path: /path/to/project
password: your-password
data_dir: .c00d              # SQLite database location

ai:
  provider: c00d              # c00d, anthropic, openai, ollama
  license_key: ""             # c00d Pro license
  api_key: ""                 # For anthropic/openai
  model: claude-sonnet-4-20250514
  ollama_url: http://localhost:11434

editor:
  theme: vs-dark
  font_size: 14
  tab_size: 4

security:
  allowed_ips: []
  require_https: false
  log_ips: true               # Default: true - logs all IPs to database
```

### API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/auth` | GET | No | Check auth status |
| `/api/auth` | POST | No | Login with password |
| `/api/auth` | DELETE | No | Logout |
| `/api/files?path=` | GET | Yes | List directory |
| `/api/file?path=` | GET | Yes | Read file |
| `/api/file?path=` | POST/PUT | Yes | Write file |
| `/api/file?path=` | DELETE | Yes | Delete file |
| `/api/file?path=` | PATCH | Yes | Rename file (body: `{"new_path":"..."}`) |
| `/api/terminal` | POST | Yes | Execute command (body: `{"command":"...","cwd":"..."}`) |
| `/api/ai` | POST | Yes | AI chat |
| `/api/git` | POST | Yes | Git operations (see below) |
| `/api/search` | POST | Yes | Search file contents |
| `/api/iplogs` | GET | Yes | View IP logs (`?view=unique&limit=100`) |
| `/api/config` | GET | Yes | Get editor/AI config |

### Git Actions (`POST /api/git`)

```json
{"action": "status"}
{"action": "stage", "file": "path/to/file"}
{"action": "unstage", "file": "path/to/file"}
{"action": "stage_all"}
{"action": "unstage_all"}
{"action": "commit", "message": "commit message"}
{"action": "push"}
{"action": "pull"}
{"action": "diff", "staged": true, "file": "optional/path"}
{"action": "discard", "file": "path/to/file"}
```

### Search (`POST /api/search`)

```json
{
  "query": "search term",
  "path": "optional/subdir",
  "is_regex": false,
  "max_results": 100,
  "file_glob": "*.js"
}
```

### Database Schema (SQLite)

```sql
-- Settings key/value store
settings (key TEXT PRIMARY KEY, value TEXT)

-- Command history
command_history (id, command, created_at)

-- AI conversation history
ai_history (id, role, content, file_context, tokens, created_at)

-- AI usage tracking
ai_usage (date TEXT PRIMARY KEY, request_count, token_count)

-- Sessions (persistent across restarts)
sessions (id TEXT PRIMARY KEY, expires_at, created_at, last_accessed)

-- IP access logs
ip_logs (id, ip_address, endpoint, method, user_agent, created_at)
```

### Security Features

- **Password protection**: Set `password` in config
- **Session persistence**: Sessions stored in SQLite, survive restarts
- **IP logging**: All requests logged with IP, endpoint, method, user-agent, timestamp
- **Path validation**: All file operations validated within `base_path`
- **No shell injection**: Git/commands use `exec.Command()` with args, not shell strings

---

## PHP Version (`/opensource/`)

### Structure

```
opensource/
├── config.php               # Default config
├── config.local.php         # User config (gitignored)
├── VERSION                  # Version number
├── public/
│   ├── index.php            # IDE interface
│   └── api.php              # API endpoint
├── src/
│   ├── Database.php         # SQLite handler
│   ├── IDE.php              # File/terminal operations
│   └── AI.php               # AI integration
├── terminal/                # Optional PTY terminal
│   ├── server.js            # WebSocket server (Node.js)
│   └── package.json
└── data/
    └── c00d.db              # SQLite database
```

### Configuration (config.local.php)

```php
<?php
return [
    'base_path' => '/var/www/project',
    'password' => 'your-password',
    'ai' => [
        'provider' => 'c00d',    // c00d, claude-cli, anthropic, openai, ollama
        'license_key' => '',
        'api_key' => '',
    ],
    'editor' => [
        'theme' => 'vs-dark',
        'font_size' => 14,
        'tab_size' => 4,
    ],
    'security' => [
        'allowed_ips' => [],
        'require_https' => true,
    ],
    'terminal' => [
        'server_port' => 3456,
    ],
];
```

### Requirements

- PHP 8.0+
- SQLite extension
- cURL extension (for AI)
- Node.js (optional, for PTY terminal)

---

## Main Website (`/public/`, `/server/`)

The c00d.com website is a separate PHP application.

### Structure

```
public/
├── index.php                # Routes to templates
├── header.php               # HTML head + nav
├── footer.php               # Footer + scripts
├── templates/
│   ├── frontpage.php        # Landing page
│   ├── pro.php              # Pro license page
│   ├── ide.php              # Web IDE
│   ├── dash.php             # User dashboard
│   └── ...
├── template-parts/
│   ├── logo.php
│   └── header-titles.php
└── download.php             # Download handler

server/
├── misc/
│   ├── config.php           # Site config
│   ├── db-connect.php       # MySQL connection
│   ├── functions.php        # Helper functions
│   ├── parameters.php       # URL routing
│   └── session-loggedin.php # Session handling
└── db-queries/
    └── user-details-loggedin.php  # User auth check
```

### Session Handling

Sessions use a 256-bit random hash stored in cookie and database:
- Cookie: `session_loggedin`
- Database: `users.user_session_hash`
- Sessions validate by hash only (IP removed for persistence across networks)

---

## Key Implementation Details

### Go: Session Persistence

Sessions are stored in SQLite, not in-memory:
```go
// internal/db/sessions.go
CreateSession(sessionID, duration) // INSERT to database
ValidateSession(sessionID) bool    // SELECT + check expiry
DeleteSession(sessionID)           // DELETE from database
```

Background cleanup runs hourly via `auth.StartCleanupRoutine()`.

### Go: IP Logging

All requests are logged when `security.log_ips: true` (default):
```go
// Middleware chain in main.go
withAuth := func(h http.HandlerFunc) http.HandlerFunc {
    return auth.WithLogging(auth.Middleware(h))
}
```

View logs via `GET /api/iplogs?view=unique&limit=100`.

### Go: Git Integration

Git commands use `exec.Command()` with separate args to prevent injection:
```go
cmd = exec.Command("git", "add", "--", req.File)
cmd.Dir = config.C.BasePath
```

### Go: File Search

Recursive search with smart defaults:
- Skips: `.git`, `node_modules`, `vendor`, `.c00d`, hidden files
- Max file size: 1MB
- Supports regex or case-insensitive substring

---

## Common Tasks

### Adding a New API Endpoint (Go)

1. Create handler in `internal/handlers/newhandler.go`
2. Import config/db packages as needed
3. Add route in `main.go`: `mux.HandleFunc("/api/new", withAuth(handlers.New))`

### Adding a Config Option (Go)

1. Add field to `Config` struct in `internal/config/config.go`
2. Add default in `ApplyDefaults()` if needed
3. Use via `config.C.YourField`

### Adding a Database Table (Go)

1. Add CREATE TABLE to `internal/db/migrations.go`
2. Add query functions in new file under `internal/db/`

---

## Build & Test

```bash
# Go version
cd go
go build -o c00d
./c00d -port 3000 -path /your/project

# PHP version - just point web server to opensource/public/
```

---

## Module Path

Go module: `github.com/c00d-ide/c00d`

All internal imports use: `github.com/c00d-ide/c00d/internal/...`
