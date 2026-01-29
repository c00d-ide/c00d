# c00d

**A web-first, self-hosted code editor. Code from anywhere - phone, tablet, or desktop.**

c00d is an open-source IDE you install on your server and access via browser. Edit files, run commands, and use AI assistance - all from any device.

## Why c00d?

- **Web-first** - Works on mobile, tablet, desktop. No app to install.
- **Self-hosted** - Your code stays on your server. Full control.
- **AI-powered** - Built-in AI assistant for code help (optional)
- **Zero config** - Just PHP. No complex setup.

## Features

- **Monaco Editor** - Same editor as VS Code
- **File Browser** - Navigate, create, rename, delete files
- **Integrated Terminal** - Run commands in your browser
- **AI Assistant** - Explain code, fix bugs, generate tests
- **Mobile Friendly** - Full functionality on any device
- **Password Protected** - Optional authentication
- **SQLite Storage** - Preferences and history saved locally

## Quick Start

```bash
# Download
curl -L https://c00d.com/download -o c00d.zip
unzip c00d.zip

# Configure (optional)
cp config.php config.local.php
nano config.local.php  # Set password, AI provider, etc.

# Access via browser
# Point your web server to the 'public' folder
# Open https://yourserver.com/c00d/
```

## Requirements

- PHP 8.0+
- SQLite extension (usually included)
- cURL extension (for AI features)

## AI Providers

c00d supports multiple AI backends. Choose what works for you:

| Provider | Setup | Cost |
|----------|-------|------|
| **c00d API** | Just works | Free: 20/day, Pro: $9/mo unlimited |
| **Claude CLI** | Install CLI, run `claude login` | Uses your Claude subscription |
| **Anthropic API** | Add API key | Pay Anthropic directly |
| **OpenAI API** | Add API key | Pay OpenAI directly |
| **Ollama** | Run Ollama locally | Free, fully private |

### Using Claude CLI (for Claude subscribers)

If you have a Claude Max or Pro subscription:

```bash
# Install Claude CLI on your server
npm install -g @anthropic-ai/claude-code

# Authenticate
claude login

# Configure c00d to use it
# In config.local.php:
'ai' => [
    'provider' => 'claude-cli',
]
```

### Using Ollama (free, private)

```bash
# Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh

# Pull a model
ollama pull codellama

# Configure c00d
'ai' => [
    'provider' => 'ollama',
    'model' => 'codellama',
]
```

## Configuration

Create `config.local.php` (won't be overwritten on updates):

```php
<?php
return [
    // Restrict to a specific directory
    'base_path' => '/var/www/myproject',

    // Password protection (recommended!)
    'password' => 'your-secure-password',

    // AI settings
    'ai' => [
        'provider' => 'c00d',      // or: claude-cli, anthropic, openai, ollama
        'license_key' => '',        // c00d Pro license for unlimited AI
        'api_key' => '',            // For anthropic/openai providers
    ],

    // Editor
    'editor' => [
        'theme' => 'vs-dark',       // vs-dark, vs-light, hc-black
        'font_size' => 14,
        'tab_size' => 4,
    ],

    // Security
    'security' => [
        'allowed_ips' => [],        // Restrict access by IP
        'require_https' => true,
    ],
];
```

## File Structure

```
c00d/
├── config.php           # Default config
├── config.local.php     # Your settings (gitignored)
├── public/
│   ├── index.php        # IDE interface
│   └── api.php          # API endpoint
├── src/
│   ├── Database.php     # SQLite handler
│   ├── IDE.php          # File/terminal operations
│   └── AI.php           # AI integration
└── data/
    └── c00d.db          # SQLite database (auto-created)
```

## Security

- **Always set a password** for production use
- **Use HTTPS** - set `require_https` to true
- **Restrict IPs** if possible via `allowed_ips`
- **Protect data folder** - `.htaccess` included, but verify
- Don't expose on public internet without authentication

## Mobile Usage

c00d is designed to work on mobile devices:

- Responsive layout adapts to screen size
- Touch-friendly file browser
- On-screen keyboard works with terminal
- Monaco editor supports mobile editing

**Tip:** Add to home screen for app-like experience.

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+S` | Save file |
| `Ctrl+P` | Quick open (coming soon) |
| `Ctrl+\`` | Toggle terminal |
| `Ctrl+B` | Toggle sidebar (coming soon) |

## Pro License

For unlimited AI access without API keys:

1. Go to [c00d.com/pro](https://c00d.com/pro)
2. Subscribe for $9/month
3. Add license key to config:

```php
'ai' => [
    'provider' => 'c00d',
    'license_key' => 'your-license-key',
]
```

## Contributing

Contributions welcome! Please:

1. Fork the repo
2. Create a feature branch
3. Submit a pull request

## License

MIT License - use it however you want.

The code is open source. The "c00d" name and logo are trademarks.

## Links

- **Website:** [c00d.com](https://c00d.com)
- **Pro License:** [c00d.com/pro](https://c00d.com/pro)
- **GitHub:** [github.com/c00d-ide/c00d](https://github.com/c00d-ide/c00d)
- **Issues:** [github.com/c00d-ide/c00d/issues](https://github.com/c00d-ide/c00d/issues)

---

Made with code by humans (and AI).
