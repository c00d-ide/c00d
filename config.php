<?php
/**
 * c00d IDE Configuration
 *
 * Copy this file to config.local.php and customize as needed.
 * config.local.php is gitignored and won't be overwritten on updates.
 */

// Version constant - read from VERSION file
define('C00D_VERSION', trim(@file_get_contents(__DIR__ . '/VERSION') ?: '1.0.0'));

return [
    // Base path for file browsing (default: directory where c00d is installed)
    // Set to a specific path to restrict access, e.g., '/var/www/myproject'
    'base_path' => dirname(__FILE__),

    // Password protection (recommended!)
    // Set a password to require authentication. Leave empty for no password.
    // For production, use a strong password!
    'password' => '',

    // Session lifetime in seconds (default: 24 hours)
    'session_lifetime' => 86400,

    // AI Configuration
    'ai' => [
        // Provider options:
        // - 'c00d'       : Use c00d.com API (free tier: 20/day, Pro: unlimited)
        // - 'claude-cli' : Use Claude CLI with your existing Claude subscription
        // - 'anthropic'  : Use Anthropic API directly (requires api_key)
        // - 'openai'     : Use OpenAI API (requires api_key)
        // - 'ollama'     : Use local Ollama (free, private, self-hosted)
        'provider' => 'c00d',

        // c00d.com Pro license key ($9/mo for unlimited AI)
        // Get yours at: https://c00d.com/pro
        'license_key' => '',

        // Or use your own API keys (for anthropic/openai providers)
        'api_key' => '',

        // Model to use
        // c00d/anthropic: claude-3-5-sonnet-20241022, claude-3-opus-20240229
        // openai: gpt-4o, gpt-4-turbo, gpt-3.5-turbo
        // ollama: llama2, codellama, mistral, etc.
        'model' => 'claude-3-5-sonnet-20241022',

        // Ollama server URL (only if using ollama provider)
        'ollama_url' => 'http://localhost:11434',

        // Claude CLI path (only if using claude-cli provider)
        // Users with Claude Max/Pro subscription can use their existing subscription
        // Install: npm install -g @anthropic-ai/claude-code
        // Then run: claude login
        'claude_cli_path' => 'claude',
    ],

    // Editor settings
    'editor' => [
        'theme' => 'vs-dark',        // vs-dark, vs-light, hc-black
        'font_size' => 14,
        'tab_size' => 4,
        'word_wrap' => 'on',         // on, off, wordWrapColumn, bounded
        'minimap' => true,
        'line_numbers' => 'on',      // on, off, relative
    ],

    // Terminal settings
    'terminal' => [
        'font_size' => 14,
        'history_size' => 1000,
    ],

    // File browser settings
    'files' => [
        'show_hidden' => true,
        'denied_paths' => ['.git', 'node_modules', 'vendor', '.env', '.env.local'],
    ],

    // Security settings
    'security' => [
        // Allowed IP addresses (empty = allow all)
        'allowed_ips' => [],

        // Enable HTTPS requirement
        'require_https' => false,
    ],
];
