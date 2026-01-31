<?php
/**
 * c00d IDE Configuration
 *
 * IMPORTANT: To customize settings, create config.local.php in the same directory.
 * config.local.php is gitignored and won't be overwritten by updates.
 *
 * Example config.local.php:
 * <?php
 * return array_merge(require __DIR__ . '/config.php', [
 *     'password' => 'your-secure-password-here',
 *     'base_path' => '/var/www/myproject',
 * ]);
 */

// Version constant - read from VERSION file
define('C00D_VERSION', trim(@file_get_contents(__DIR__ . '/VERSION') ?: '1.0.0'));

// Auto-generate secure password on first install
// Password is stored in data/.generated_password for persistence
// Fallback: deterministic password based on install path if file ops fail
$_c00d_password = '';
$_c00d_password_file = __DIR__ . '/data/.generated_password';
if (!file_exists($_c00d_password_file)) {
    // Generate secure 16-character password
    $_c00d_password = bin2hex(random_bytes(8)); // 16 hex characters
    @mkdir(__DIR__ . '/data', 0755, true);
    @file_put_contents($_c00d_password_file, $_c00d_password);
    @chmod($_c00d_password_file, 0600); // Restrict access
} else {
    $_c00d_password = trim(@file_get_contents($_c00d_password_file) ?: '');
}
// Fallback: if password is still empty (file ops failed), generate from install path
if (empty($_c00d_password)) {
    $_c00d_password = substr(hash('sha256', __DIR__ . '|c00d-fallback-salt'), 0, 16);
}

return [
    // =========================================================================
    // SECURITY SETTINGS (Configure these first!)
    // =========================================================================

    // Password protection - AUTO-GENERATED on fresh install
    // Your password is stored in: data/.generated_password
    // To set a custom password, create config.local.php:
    //   <?php
    //   return array_merge(require __DIR__ . '/config.php', [
    //       'password' => 'your-custom-password-here',
    //   ]);
    'password' => $_c00d_password,

    // Session lifetime in seconds (default: 24 hours)
    // How long you stay logged in before needing to re-enter password
    'session_lifetime' => 86400,

    // =========================================================================
    // FILE BROWSER SETTINGS
    // =========================================================================

    // Base path - The ROOT FOLDER for file browsing
    // This is the top-level directory users can access. They CANNOT navigate
    // above this folder. By default, it's the directory where c00d is installed.
    //
    // Examples:
    //   dirname(__FILE__)           - c00d installation folder (default)
    //   '/var/www/html'             - Your web root
    //   '/home/user/projects'       - A projects folder
    //   '/var/www/mysite.com'       - A specific website
    //
    // To change, add to config.local.php:
    //   'base_path' => '/your/desired/path',
    'base_path' => dirname(__FILE__),

    // File browser options
    'files' => [
        // Show hidden files (starting with .)
        'show_hidden' => true,

        // Paths that are blocked from viewing/editing (security)
        // Users cannot access any path containing these folder names
        'denied_paths' => ['.git', 'node_modules', 'vendor', '.env', '.env.local'],
    ],

    // Additional security settings
    'security' => [
        // Restrict access to specific IP addresses (empty = allow all)
        // Example: ['192.168.1.100', '10.0.0.50']
        'allowed_ips' => [],

        // Require HTTPS connection (recommended for production)
        'require_https' => false,
    ],

    // =========================================================================
    // AI CONFIGURATION
    // =========================================================================

    'ai' => [
        // AI Provider options:
        // - 'c00d'       : Use c00d.com API (free tier: 20 requests/day, Pro: unlimited)
        // - 'claude-cli' : Use Claude CLI with your existing Claude Max/Pro subscription
        // - 'anthropic'  : Use Anthropic API directly (requires api_key below)
        // - 'openai'     : Use OpenAI API (requires api_key below)
        // - 'ollama'     : Use local Ollama server (free, private, self-hosted)
        'provider' => 'c00d',

        // c00d.com Pro license key ($9/mo for unlimited AI)
        // Get yours at: https://c00d.com/pro
        'license_key' => '',

        // Your own API key (required for 'anthropic' or 'openai' providers)
        'api_key' => '',

        // AI Model to use
        // c00d/anthropic: claude-sonnet-4-20250514, claude-3-5-sonnet-20241022
        // openai: gpt-4o, gpt-4-turbo, gpt-3.5-turbo
        // ollama: llama2, codellama, mistral, deepseek-coder, etc.
        'model' => 'claude-sonnet-4-20250514',

        // Ollama server URL (only needed if using 'ollama' provider)
        'ollama_url' => 'http://localhost:11434',

        // Claude CLI executable path (only needed if using 'claude-cli' provider)
        // Users with Claude Max/Pro subscription can leverage their existing plan
        // Install: npm install -g @anthropic-ai/claude-code
        // Then run: claude login
        'claude_cli_path' => 'claude',
    ],

    // =========================================================================
    // EDITOR SETTINGS
    // =========================================================================

    'editor' => [
        'theme' => 'vs-dark',        // vs-dark, vs-light, hc-black
        'font_size' => 14,
        'tab_size' => 4,
        'word_wrap' => 'on',         // on, off, wordWrapColumn, bounded
        'minimap' => true,
        'line_numbers' => 'on',      // on, off, relative
    ],

    // =========================================================================
    // TERMINAL SETTINGS
    // =========================================================================

    'terminal' => [
        'font_size' => 14,
        'history_size' => 1000,      // Number of commands to remember

        // WebSocket PTY Terminal (full interactive terminal)
        // WARNING: When enabled, users can navigate OUTSIDE the base_path!
        // The WebSocket terminal provides a real shell that can cd anywhere.
        // Only enable if you trust users or the system user has limited permissions.
        // When disabled (default), only the basic PHP terminal is available,
        // which strictly respects base_path restrictions.
        'websocket_enabled' => false,

        // PTY Terminal Server port (only used if websocket_enabled is true)
        // Connection methods tried in order:
        // 1. WebSocket proxy: wss://yourdomain/ws/terminal (requires web server config)
        // 2. Direct port: wss://yourdomain:3456 (requires firewall to allow this port)
        'server_port' => 3456,
    ],
];
