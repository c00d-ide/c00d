<?php
/**
 * c00d IDE - Self-Hosted Code Editor
 * https://github.com/c00d/ide
 *
 * Open source, self-hosted web IDE with optional AI features.
 */

// Load config
$configFile = file_exists(__DIR__ . '/../config.local.php')
    ? __DIR__ . '/../config.local.php'
    : __DIR__ . '/../config.php';
$config = require $configFile;

$editorConfig = $config['editor'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>c00d IDE</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' shape-rendering='crispEdges'><rect x='4' y='2' width='8' height='12' fill='%23ff0000'/><rect x='6' y='4' width='4' height='8' fill='%23000000'/></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.min.css">
    <style>
        @font-face {
            font-family: 'Tiny5';
            src: url('fonts/Tiny5-Regular.ttf') format('truetype');
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-dark: #000000;
            --bg-darker: #000000;
            --bg-sidebar: #0a0a0a;
            --border: #222222;
            --text: #d4d4d4;
            --text-dim: #808080;
            --accent: #ff0000;
            --accent-red: #f44;
            --success: #4ec9b0;
            --warning: #dcdcaa;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-dark);
            color: var(--text);
            overflow: hidden;
            height: 100vh;
        }

        /* Login Screen */
        #login-screen {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            z-index: 1000;
        }

        .login-box {
            background: var(--bg-darker);
            padding: 40px;
            border: 1px solid var(--border);
            text-align: center;
            max-width: 400px;
        }

        .login-box h1 {
            font-family: 'Tiny5', monospace;
            font-size: 48px;
            margin-bottom: 10px;
            color: var(--accent);
            letter-spacing: 2px;
        }

        .login-box p {
            color: var(--text-dim);
            margin-bottom: 20px;
        }

        .login-box input {
            width: 100%;
            padding: 12px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 16px;
            margin-bottom: 15px;
        }

        .login-box button {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .login-error {
            color: var(--accent-red);
            margin-bottom: 15px;
        }

        /* Main Layout */
        #app {
            display: none;
            height: 100vh;
            flex-direction: column;
        }

        #app.active {
            display: flex;
        }

        /* Toolbar */
        #toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border);
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toolbar-title {
            font-family: 'Tiny5', monospace;
            font-size: 24px;
            color: var(--accent);
            letter-spacing: 1px;
        }

        .toolbar-btn {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 13px;
        }

        .toolbar-btn:hover, .toolbar-btn.active {
            background: var(--accent);
            border-color: var(--accent);
        }

        .ai-status {
            font-size: 12px;
            color: var(--text-dim);
        }

        .ai-status.pro {
            color: var(--success);
        }

        /* Main Content */
        #main {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* Sidebar */
        #sidebar {
            width: 250px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 5px;
        }

        #path-input {
            flex: 1;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 6px 10px;
            font-size: 12px;
            font-family: monospace;
        }

        .sidebar-btn {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 6px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        #file-list {
            flex: 1;
            overflow-y: auto;
        }

        .file-item {
            padding: 6px 15px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-item:hover {
            background: rgba(255,255,255,0.05);
        }

        .file-item.directory { color: var(--warning); }
        .file-item .icon { width: 16px; text-align: center; }

        .sidebar-actions {
            padding: 10px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 5px;
        }

        .sidebar-actions button {
            flex: 1;
            font-size: 11px;
        }

        /* Editor Area */
        #editor-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Tabs */
        #tabs {
            display: flex;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
        }

        .tab {
            padding: 8px 15px;
            background: var(--bg-dark);
            border-right: 1px solid var(--border);
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .tab:hover { background: var(--bg-darker); }
        .tab.active { background: var(--bg-dark); border-bottom: 2px solid var(--accent); }
        .tab.unsaved::after { content: '●'; color: var(--accent-red); margin-left: 5px; }
        .tab-close { opacity: 0.5; font-size: 16px; }
        .tab-close:hover { opacity: 1; color: var(--accent-red); }

        /* Monaco */
        #editor-wrapper {
            flex: 1;
            position: relative;
        }

        #monaco-container {
            position: absolute;
            inset: 0;
            display: none;
        }

        #monaco-container.active {
            display: block;
        }

        #editor-status {
            display: flex;
            gap: 20px;
            padding: 4px 15px;
            background: var(--bg-dark);
            font-size: 12px;
        }

        /* Terminal */
        #terminal-panel {
            height: 250px;
            min-height: 100px;
            max-height: calc(100vh - 100px);
            background: var(--bg-dark);
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        #terminal-panel.hidden { display: none; }

        #terminal-panel.fullscreen {
            height: calc(100vh - 50px) !important;
        }

        .terminal-resizer {
            position: absolute;
            top: -4px;
            left: 0;
            right: 0;
            height: 8px;
            cursor: ns-resize;
            background: transparent;
            z-index: 10;
        }

        .terminal-resizer:hover,
        .terminal-resizer.dragging {
            background: var(--accent);
            opacity: 0.5;
        }

        .terminal-header {
            padding: 5px 15px;
            background: var(--bg-darker);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .terminal-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terminal-header-right {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .terminal-size-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 2px 6px;
            font-size: 14px;
            line-height: 1;
        }

        .terminal-size-btn:hover {
            color: var(--text);
        }

        .terminal-server-prompt {
            padding: 20px;
            text-align: center;
            color: var(--text-dim);
        }

        .terminal-server-prompt p {
            margin-bottom: 15px;
            font-size: 13px;
        }

        .terminal-server-btn {
            padding: 8px 16px;
            background: var(--success);
            border: none;
            color: #000;
            font-size: 13px;
            cursor: pointer;
            margin: 5px;
        }

        .terminal-server-btn:hover {
            opacity: 0.9;
        }

        .terminal-server-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .terminal-server-btn.stop {
            background: var(--accent-red);
            color: #fff;
        }

        #terminal-container {
            flex: 1;
            padding: 5px;
            overflow: hidden;
        }

        #terminal-container .xterm {
            height: 100%;
        }

        /* Simple terminal fallback */
        #simple-terminal {
            display: none;
            flex-direction: column;
            height: 100%;
        }

        #simple-terminal.active {
            display: flex;
        }

        #terminal-output {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            line-height: 1.4;
        }

        .cmd-line { color: var(--success); }
        .cmd-output { color: var(--text); }
        .cmd-error { color: var(--accent-red); }

        #terminal-input-line {
            display: flex;
            padding: 8px 10px;
            background: var(--bg-darker);
            border-top: 1px solid var(--border);
        }

        #terminal-prompt {
            color: var(--success);
            margin-right: 10px;
            font-family: monospace;
        }

        #terminal-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text);
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            outline: none;
        }

        .terminal-status {
            font-size: 11px;
            color: var(--text-dim);
        }

        .terminal-status.connected { color: var(--success); }
        .terminal-status.simple { color: var(--warning); }
        .terminal-status.disconnected { color: var(--accent-red); }

        /* AI Panel */
        #ai-panel {
            width: 350px;
            background: var(--bg-sidebar);
            border-left: 1px solid var(--border);
            display: none;
            flex-direction: column;
        }

        #ai-panel.active { display: flex; }

        .ai-header {
            padding: 10px 15px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .ai-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            line-height: 1.5;
        }

        .ai-message.user {
            background: var(--accent);
            margin-left: 20px;
        }

        .ai-message.assistant {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            margin-right: 20px;
        }

        .ai-message pre {
            background: var(--bg-darker);
            padding: 10px;
            margin: 10px 0;
            overflow-x: auto;
            font-family: monospace;
            font-size: 13px;
        }

        .ai-message code {
            background: var(--bg-darker);
            padding: 2px 6px;
            font-family: monospace;
        }

        #ai-input-area {
            padding: 10px;
            border-top: 1px solid var(--border);
        }

        #ai-input {
            width: 100%;
            padding: 10px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 14px;
            resize: none;
            height: 60px;
        }

        .ai-actions {
            display: flex;
            gap: 5px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .ai-action-btn {
            padding: 5px 10px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 11px;
            cursor: pointer;
        }

        .ai-action-btn:hover {
            background: var(--accent);
        }

        /* Git Panel */
        #git-panel {
            width: 300px;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        #git-panel.active { display: flex; }

        .git-header {
            padding: 10px 15px;
            background: var(--bg-darker);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .git-branch {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .git-branch-icon { color: var(--success); }

        .git-sync-status {
            font-size: 11px;
            color: var(--text-dim);
        }

        .git-sync-status.ahead { color: var(--success); }
        .git-sync-status.behind { color: var(--warning); }

        .git-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .git-section {
            margin-bottom: 15px;
        }

        .git-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: var(--text-dim);
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border);
        }

        .git-section-actions {
            display: flex;
            gap: 5px;
        }

        .git-section-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 11px;
            padding: 2px 5px;
        }

        .git-section-btn:hover { color: var(--text); }

        .git-file {
            display: flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
            border-radius: 3px;
            gap: 8px;
        }

        .git-file:hover { background: rgba(255,255,255,0.05); }

        .git-file-status {
            width: 14px;
            height: 14px;
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .git-file-status.M { background: var(--warning); color: #000; }
        .git-file-status.A { background: var(--success); color: #000; }
        .git-file-status.D { background: var(--accent-red); color: #fff; }
        .git-file-status.R { background: #569cd6; color: #fff; }
        .git-file-status.new { background: var(--success); color: #000; }

        .git-file-name {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .git-file-actions {
            display: none;
            gap: 3px;
        }

        .git-file:hover .git-file-actions { display: flex; }

        .git-file-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 12px;
            padding: 2px;
        }

        .git-file-btn:hover { color: var(--text); }
        .git-file-btn.danger:hover { color: var(--accent-red); }

        .git-commit-area {
            padding: 10px;
            border-top: 1px solid var(--border);
        }

        #git-commit-message {
            width: 100%;
            padding: 8px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
            resize: none;
            height: 60px;
            margin-bottom: 8px;
        }

        .git-commit-actions {
            display: flex;
            gap: 5px;
        }

        .git-btn {
            flex: 1;
            padding: 6px 10px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 12px;
            cursor: pointer;
        }

        .git-btn:hover { background: var(--border); }
        .git-btn.primary { background: var(--accent); border-color: var(--accent); }
        .git-btn.primary:hover { background: #cc0000; }
        .git-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .git-empty {
            text-align: center;
            color: var(--text-dim);
            font-size: 12px;
            padding: 20px;
        }

        .git-not-repo {
            text-align: center;
            color: var(--text-dim);
            padding: 40px 20px;
        }

        .git-not-repo p { margin-bottom: 10px; font-size: 13px; }

        /* Resizers */
        .resizer {
            background: var(--border);
            cursor: ns-resize;
            height: 4px;
        }

        /* Welcome */
        #welcome {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-dim);
            height: 100%;
        }

        #welcome h2 { color: var(--accent); margin-bottom: 15px; }
        #welcome p { margin-bottom: 10px; }
        #welcome kbd {
            background: var(--bg-darker);
            padding: 3px 8px;
            border-radius: 3px;
            font-family: monospace;
        }

        /* Update Button */
        .toolbar-btn.update-btn {
            background: linear-gradient(90deg, #b8860b, #daa520);
            color: #000;
            border: none;
            font-weight: bold;
            animation: pulse-update 2s infinite;
        }

        .toolbar-btn.update-btn:hover {
            background: linear-gradient(90deg, #daa520, #ffd700);
        }

        @keyframes pulse-update {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <!-- Login Screen -->
    <div id="login-screen" style="<?php echo empty($config['password']) ? 'display:none' : ''; ?>">
        <div class="login-box">
            <div style="margin-bottom: 20px;">
                <svg width="64" height="64" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges">
                    <rect x="1" y="1" width="14" height="12"/>
                    <rect x="2" y="2" width="12" height="8" fill="#000000"/>
                    <rect x="3" y="3" width="2" height="1" fill="#ff0000"/>
                    <rect x="6" y="3" width="4" height="1" fill="#ff0000"/>
                    <rect x="3" y="5" width="3" height="1" fill="#4ec9b0"/>
                    <rect x="7" y="5" width="5" height="1" fill="#dcdcaa"/>
                    <rect x="5" y="13" width="6" height="2"/>
                </svg>
            </div>
            <h1>c00d IDE</h1>
            <p>Enter password to continue</p>
            <div id="login-error" class="login-error" style="display:none"></div>
            <input type="password" id="login-password" placeholder="Password" autofocus>
            <button onclick="login()">Login</button>
            <p style="margin-top:20px;font-size:11px;color:#666">
                Password location: <code style="background:#222;padding:2px 6px;border-radius:3px">data/.generated_password</code><br>
                <span style="color:#555">If file not found, run: <code style="background:#222;padding:2px 4px;border-radius:3px;font-size:10px">php -r "echo substr(hash('sha256', realpath('.'). '|c00d-fallback-salt'), 0, 16);"</code></span>
            </p>
        </div>
    </div>

    <!-- Main App -->
    <div id="app" class="<?php echo empty($config['password']) ? 'active' : ''; ?>">
        <!-- Toolbar -->
        <div id="toolbar">
            <div class="toolbar-left">
                <span class="toolbar-title" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="24" height="24" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges">
                        <rect x="1" y="1" width="14" height="12"/>
                        <rect x="2" y="2" width="12" height="8" fill="#000000"/>
                        <rect x="3" y="3" width="2" height="1" fill="#ff0000"/>
                        <rect x="6" y="3" width="4" height="1" fill="#ff0000"/>
                        <rect x="3" y="5" width="3" height="1" fill="#4ec9b0"/>
                        <rect x="7" y="5" width="5" height="1" fill="#dcdcaa"/>
                        <rect x="5" y="13" width="6" height="2"/>
                    </svg>
                    c00d
                </span>
                <span id="current-path" style="font-size: 12px; color: var(--text-dim);"></span>
            </div>
            <div class="toolbar-right">
                <span id="ai-status" class="ai-status">AI: Loading...</span>
                <button class="toolbar-btn update-btn" onclick="showUpdateDialog()" id="update-btn" style="display:none;" title="Update available">Update</button>
                <button class="toolbar-btn" onclick="toggleGit()" id="git-btn">Git</button>
                <button class="toolbar-btn" onclick="toggleTerminal()">Terminal</button>
                <button class="toolbar-btn" onclick="toggleAI()">AI Chat</button>
                <button class="toolbar-btn" onclick="saveFile()">Save</button>
            </div>
        </div>

        <!-- Update Banner -->
        <!-- Main Content -->
        <div id="main">
            <!-- Sidebar -->
            <div id="sidebar">
                <div class="sidebar-header">
                    <input type="text" id="path-input" value="." placeholder=".">
                    <button class="sidebar-btn" onclick="loadDirectory()">Go</button>
                </div>
                <div id="file-list"></div>
                <div class="sidebar-actions">
                    <button class="sidebar-btn" onclick="createFile()">+ File</button>
                    <button class="sidebar-btn" onclick="createFolder()">+ Folder</button>
                    <button class="sidebar-btn" onclick="loadDirectory()">Refresh</button>
                </div>
            </div>

            <!-- Git Panel -->
            <div id="git-panel">
                <div class="git-header">
                    <div class="git-branch">
                        <span class="git-branch-icon">⎇</span>
                        <span id="git-branch-name">main</span>
                        <span id="git-sync-status" class="git-sync-status"></span>
                    </div>
                    <button class="sidebar-btn" onclick="toggleGit()">×</button>
                </div>
                <div id="git-content" class="git-content">
                    <!-- Content populated by JS -->
                </div>
                <div class="git-commit-area">
                    <textarea id="git-commit-message" placeholder="Commit message..."></textarea>
                    <div class="git-commit-actions">
                        <button class="git-btn" onclick="gitPull()" title="Pull from remote">↓ Pull</button>
                        <button class="git-btn" onclick="gitPush()" title="Push to remote">↑ Push</button>
                        <button class="git-btn primary" onclick="gitCommit()" id="git-commit-btn">Commit</button>
                    </div>
                </div>
            </div>

            <!-- Editor Area -->
            <div id="editor-area">
                <div id="tabs"></div>
                <div id="editor-wrapper">
                    <div id="monaco-container"></div>
                    <div id="welcome">
                        <div>
                            <svg width="80" height="80" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges" style="margin-bottom: 20px;">
                                <rect x="1" y="1" width="14" height="12"/>
                                <rect x="2" y="2" width="12" height="8" fill="#000000"/>
                                <rect x="3" y="3" width="2" height="1" fill="#ff0000"/>
                                <rect x="6" y="3" width="4" height="1" fill="#ff0000"/>
                                <rect x="3" y="5" width="3" height="1" fill="#4ec9b0"/>
                                <rect x="7" y="5" width="5" height="1" fill="#dcdcaa"/>
                                <rect x="5" y="13" width="6" height="2"/>
                            </svg>
                            <h2>Welcome to c00d IDE</h2>
                            <p>Select a file from the sidebar to start editing</p>
                            <p><kbd>Alt+S</kbd> Save &nbsp; <kbd>Alt+P</kbd> Quick Open &nbsp; <kbd>Alt+B</kbd> Sidebar &nbsp; <kbd>Alt+T</kbd> Terminal &nbsp; <kbd>Alt+G</kbd> Git &nbsp; <kbd>Alt+A</kbd> AI</p>
                        </div>
                    </div>
                </div>
                <div id="editor-status">
                    <span id="status-lang">-</span>
                    <span id="status-pos">Ln 1, Col 1</span>
                    <span id="status-saved">-</span>
                </div>
            </div>

            <!-- AI Panel -->
            <div id="ai-panel">
                <div class="ai-header">
                    <span>AI Assistant</span>
                    <button class="sidebar-btn" onclick="toggleAI()">×</button>
                </div>
                <div id="ai-messages"></div>
                <div id="ai-input-area">
                    <textarea id="ai-input" placeholder="Ask AI about your code..."></textarea>
                    <div class="ai-actions">
                        <button class="ai-action-btn" onclick="aiExplain()">Explain</button>
                        <button class="ai-action-btn" onclick="aiImprove()">Improve</button>
                        <button class="ai-action-btn" onclick="aiTests()">Tests</button>
                        <button class="ai-action-btn" onclick="aiSend()">Send</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terminal -->
        <div id="terminal-panel" class="hidden">
            <div class="terminal-resizer" id="terminal-resizer"></div>
            <div class="terminal-header">
                <div class="terminal-header-left">
                    <span>Terminal</span>
                    <span id="terminal-status" class="terminal-status">Connecting...</span>
                </div>
                <div class="terminal-header-right">
                    <button class="terminal-size-btn" onclick="toggleTerminalFullscreen()" title="Toggle fullscreen (Shift+Esc)">
                        <span id="terminal-fullscreen-icon">↑</span>
                    </button>
                    <button class="sidebar-btn" onclick="toggleTerminal()">×</button>
                </div>
            </div>
            <!-- xterm.js terminal (if WebSocket available) -->
            <div id="terminal-container"></div>
            <!-- Simple terminal fallback -->
            <div id="simple-terminal">
                <div id="terminal-output"></div>
                <div id="terminal-input-line">
                    <span id="terminal-prompt">$</span>
                    <input type="text" id="terminal-input" placeholder="Type a command...">
                </div>
            </div>
        </div>
    </div>

    <!-- xterm.js (must load BEFORE Monaco's AMD loader to avoid conflicts) -->
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    <!-- Monaco Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
    <script>
        // State
        let editor = null;
        let openTabs = [];
        let activeTab = null;
        let currentPath = '/';
        let term = null;
        let terminalSocket = null;
        let fitAddon = null;
        let terminalMode = null; // 'websocket' or 'simple'
        let commandHistory = [];
        let historyIndex = -1;

        // Terminal configuration
        const TERMINAL_WEBSOCKET_ENABLED = <?php echo ($config['terminal']['websocket_enabled'] ?? false) ? 'true' : 'false'; ?>;
        const TERMINAL_WS_PROXY = (window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.host + '/ws/terminal';
        const TERMINAL_WS_DIRECT_PORT = <?php echo $config['terminal']['server_port'] ?? 3456; ?>;
        const TERMINAL_WS_DIRECT = (window.location.protocol === 'https:' ? 'wss://' : 'ws://') + window.location.hostname + ':' + TERMINAL_WS_DIRECT_PORT;

        // Config
        const editorConfig = <?php echo json_encode($editorConfig); ?>;

        // Pixel art icons
        const folderIcon = `<svg width="16" height="16" viewBox="0 0 16 16" fill="#dcdcaa" shape-rendering="crispEdges">
            <rect x="1" y="3" width="6" height="2"/>
            <rect x="1" y="5" width="14" height="10"/>
            <rect x="2" y="6" width="12" height="8" fill="#000000"/>
        </svg>`;

        const fileIcon = `<svg width="16" height="16" viewBox="0 0 16 16" fill="#d4d4d4" shape-rendering="crispEdges">
            <rect x="2" y="1" width="9" height="14"/>
            <rect x="11" y="1" width="3" height="3"/>
            <rect x="11" y="4" width="3" height="11"/>
            <rect x="3" y="2" width="7" height="12" fill="#000000"/>
            <rect x="4" y="4" width="5" height="1" fill="#808080"/>
            <rect x="4" y="6" width="6" height="1" fill="#808080"/>
            <rect x="4" y="8" width="4" height="1" fill="#808080"/>
        </svg>`;

        const c00dLogo = `<svg width="24" height="24" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges">
            <rect x="1" y="1" width="14" height="12"/>
            <rect x="2" y="2" width="12" height="8" fill="#000000"/>
            <rect x="3" y="3" width="2" height="1" fill="#ff0000"/>
            <rect x="6" y="3" width="4" height="1" fill="#ff0000"/>
            <rect x="3" y="5" width="3" height="1" fill="#4ec9b0"/>
            <rect x="7" y="5" width="5" height="1" fill="#dcdcaa"/>
            <rect x="5" y="13" width="6" height="2"/>
        </svg>`;

        // Initialize
        document.addEventListener('DOMContentLoaded', init);

        async function init() {
            // Check auth
            const info = await api('info');
            if (info.needs_auth) {
                document.getElementById('login-screen').style.display = 'flex';
                document.getElementById('app').classList.remove('active');
                return;
            }

            // Update AI status
            updateAIStatus();

            // Check for updates (non-blocking)
            checkForUpdates();

            // Initialize Monaco
            require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' } });
            require(['vs/editor/editor.main'], () => {
                // Define OLED-friendly theme with pure black background
                monaco.editor.defineTheme('c00d-dark', {
                    base: 'vs-dark',
                    inherit: true,
                    rules: [],
                    colors: {
                        'editor.background': '#000000',
                        'editor.lineHighlightBackground': '#0a0a0a',
                        'editorLineNumber.foreground': '#555555',
                        'editorLineNumber.activeForeground': '#ffffff',
                        'editor.selectionBackground': '#264f78',
                        'editorCursor.foreground': '#ffffff',
                        'editorWhitespace.foreground': '#333333',
                        'editorIndentGuide.background': '#222222',
                        'editorIndentGuide.activeBackground': '#444444',
                        'editorGutter.background': '#000000',
                        'minimap.background': '#000000',
                        'scrollbarSlider.background': '#333333',
                        'scrollbarSlider.hoverBackground': '#444444',
                        'scrollbarSlider.activeBackground': '#555555',
                    }
                });

                editor = monaco.editor.create(document.getElementById('monaco-container'), {
                    value: '',
                    language: 'plaintext',
                    theme: 'c00d-dark',
                    fontSize: editorConfig.font_size || 14,
                    tabSize: editorConfig.tab_size || 4,
                    wordWrap: editorConfig.word_wrap || 'on',
                    minimap: { enabled: editorConfig.minimap !== false },
                    lineNumbers: editorConfig.line_numbers || 'on',
                    automaticLayout: true,
                    scrollBeyondLastLine: false,
                });

                // Track cursor
                editor.onDidChangeCursorPosition(e => {
                    document.getElementById('status-pos').textContent =
                        `Ln ${e.position.lineNumber}, Col ${e.position.column}`;
                });

                // Track changes
                editor.onDidChangeModelContent(() => {
                    if (activeTab) {
                        activeTab.unsaved = true;
                        renderTabs();
                        document.getElementById('status-saved').textContent = 'Modified';
                    }
                });

                // Keyboard shortcuts (using Alt to avoid browser conflicts)
                editor.addCommand(monaco.KeyMod.Alt | monaco.KeyCode.KeyS, saveFile);
            });

            // Load directory
            loadDirectory();

            // Terminal is lazy-loaded when first opened

            // AI input
            document.getElementById('ai-input').addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    aiSend();
                }
            });

            // Path input
            document.getElementById('path-input').addEventListener('keydown', e => {
                if (e.key === 'Enter') loadDirectory();
            });

            // Global shortcuts (using Alt to avoid browser conflicts)
            document.addEventListener('keydown', e => {
                if (e.altKey && !e.ctrlKey && !e.metaKey) {
                    switch(e.key.toLowerCase()) {
                        case 't':
                            e.preventDefault();
                            toggleTerminal();
                            break;
                        case 's':
                            e.preventDefault();
                            saveFile();
                            break;
                        case 'p':
                            e.preventDefault();
                            openQuickOpen();
                            break;
                        case 'g':
                            e.preventDefault();
                            toggleGit();
                            break;
                        case 'a':
                            e.preventDefault();
                            toggleAI();
                            break;
                        case 'b':
                            e.preventDefault();
                            toggleSidebar();
                            break;
                    }
                }
            });
        }

        // API helper
        async function api(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const [key, value] of Object.entries(data)) {
                formData.append(key, value);
            }

            const response = await fetch('api.php', { method: 'POST', body: formData });
            return response.json();
        }

        // Update System
        let updateInfo = null;

        async function checkForUpdates() {
            // Check if dismissed recently (7 days)
            const dismissedUntil = localStorage.getItem('c00d_update_dismissed_until');
            if (dismissedUntil && Date.now() < parseInt(dismissedUntil)) {
                return;
            }

            // Check if we already checked recently (1 hour)
            const lastCheck = localStorage.getItem('c00d_update_last_check');
            if (lastCheck && Date.now() - parseInt(lastCheck) < 3600000) {
                // Use cached result
                const cached = localStorage.getItem('c00d_update_info');
                if (cached) {
                    updateInfo = JSON.parse(cached);
                    if (updateInfo.update_available) {
                        showUpdateBanner(updateInfo.remote_version);
                    }
                }
                return;
            }

            try {
                const result = await api('check_update');
                localStorage.setItem('c00d_update_last_check', Date.now().toString());

                if (result.success && result.update_available) {
                    updateInfo = result;
                    localStorage.setItem('c00d_update_info', JSON.stringify(result));
                    showUpdateBanner(result.remote_version);
                } else {
                    localStorage.removeItem('c00d_update_info');
                }
            } catch (e) {
                // Silently fail - update check is non-critical
                console.log('Update check failed:', e);
            }
        }

        function showUpdateBanner(version) {
            const btn = document.getElementById('update-btn');
            btn.style.display = 'inline-block';
            btn.textContent = `Update (${version})`;
            btn.title = `Update available: v${version}\nClick to update`;
        }

        function hideUpdateBanner() {
            document.getElementById('update-btn').style.display = 'none';
        }

        function showUpdateDialog() {
            if (!updateInfo) return;

            const msg = `Update available!\n\n` +
                `Current: v${updateInfo.local_version}\n` +
                `New: v${updateInfo.remote_version}\n\n` +
                `${updateInfo.changelog || ''}\n\n` +
                `Click OK to update now, or Cancel to dismiss for 7 days.`;

            if (confirm(msg)) {
                performUpdate();
            } else {
                // Dismiss for 7 days
                const sevenDays = 7 * 24 * 60 * 60 * 1000;
                localStorage.setItem('c00d_update_dismissed_until', (Date.now() + sevenDays).toString());
                hideUpdateBanner();
            }
        }

        async function performUpdate() {
            if (!updateInfo) return;

            const btn = document.getElementById('update-btn');
            const originalText = btn.textContent;
            btn.textContent = 'Updating...';
            btn.disabled = true;

            try {
                const result = await api('perform_update');

                if (result.success) {
                    // Clear cached update info
                    localStorage.removeItem('c00d_update_info');
                    localStorage.removeItem('c00d_update_last_check');

                    alert(`Update successful!\n\nNew version: ${result.new_version}\nBackup saved to: ${result.backup_path}\n\nThe page will now reload.`);
                    window.location.reload();
                } else {
                    alert('Update failed: ' + (result.error || 'Unknown error'));
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            } catch (e) {
                alert('Update failed: ' + e.message);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }

        // Auth
        async function login() {
            const password = document.getElementById('login-password').value;
            const result = await api('login', { password });

            if (result.success) {
                document.getElementById('login-screen').style.display = 'none';
                document.getElementById('app').classList.add('active');
                init();
            } else {
                document.getElementById('login-error').textContent = result.error;
                document.getElementById('login-error').style.display = 'block';
            }
        }

        document.getElementById('login-password')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') login();
        });

        // File Browser
        async function loadDirectory(path) {
            path = path || document.getElementById('path-input').value || '.';
            const result = await api('list', { path });

            if (result.success) {
                currentPath = result.path;
                document.getElementById('path-input').value = result.relative_path || '.';
                document.getElementById('current-path').textContent = result.relative_path || '.';

                const list = document.getElementById('file-list');
                list.innerHTML = result.items.map(item => `
                    <div class="file-item ${item.is_dir ? 'directory' : ''}"
                         onclick="${item.is_dir ? `loadDirectory('${item.path}')` : `openFile('${item.path}')`}">
                        <span class="icon">${item.is_dir ? folderIcon : fileIcon}</span>
                        <span>${escapeHtml(item.name)}</span>
                    </div>
                `).join('');
            }
        }

        async function openFile(path) {
            // Check if already open
            let tab = openTabs.find(t => t.path === path);

            if (!tab) {
                const result = await api('read', { path });
                if (!result.success) {
                    alert(result.error);
                    return;
                }

                if (result.is_binary) {
                    alert('Cannot edit binary files');
                    return;
                }

                tab = {
                    path: result.path,
                    name: result.path.split('/').pop(),
                    content: result.content,
                    language: result.language,
                    unsaved: false,
                };
                openTabs.push(tab);
            }

            switchTab(tab);
            document.getElementById('welcome').style.display = 'none';
            document.getElementById('monaco-container').classList.add('active');
        }

        function switchTab(tab) {
            activeTab = tab;
            editor.setValue(tab.content);
            monaco.editor.setModelLanguage(editor.getModel(), tab.language);
            document.getElementById('status-lang').textContent = tab.language;
            document.getElementById('status-saved').textContent = tab.unsaved ? 'Modified' : 'Saved';
            renderTabs();
        }

        function renderTabs() {
            document.getElementById('tabs').innerHTML = openTabs.map(tab => `
                <div class="tab ${tab === activeTab ? 'active' : ''} ${tab.unsaved ? 'unsaved' : ''}"
                     onclick="switchTab(openTabs.find(t => t.path === '${tab.path}'))">
                    <span>${escapeHtml(tab.name)}</span>
                    <span class="tab-close" onclick="event.stopPropagation(); closeTab('${tab.path}')">×</span>
                </div>
            `).join('');
        }

        function closeTab(path) {
            const tab = openTabs.find(t => t.path === path);
            if (tab?.unsaved && !confirm('Unsaved changes. Close anyway?')) return;

            openTabs = openTabs.filter(t => t.path !== path);

            if (tab === activeTab) {
                if (openTabs.length > 0) {
                    switchTab(openTabs[openTabs.length - 1]);
                } else {
                    activeTab = null;
                    editor.setValue('');
                    document.getElementById('welcome').style.display = 'flex';
                    document.getElementById('monaco-container').classList.remove('active');
                }
            }
            renderTabs();
        }

        async function saveFile() {
            if (!activeTab) return;

            activeTab.content = editor.getValue();
            document.getElementById('status-saved').textContent = 'Saving...';

            const result = await api('write', {
                path: activeTab.path,
                content: btoa(unescape(encodeURIComponent(activeTab.content))),
                base64: '1',
            });

            if (result.success) {
                activeTab.unsaved = false;
                document.getElementById('status-saved').textContent = 'Saved';
                renderTabs();
            } else {
                document.getElementById('status-saved').textContent = 'Save failed!';
                alert(result.error);
            }
        }

        async function createFile() {
            const name = prompt('File name:');
            if (!name) return;

            const path = currentPath + '/' + name;
            const result = await api('write', { path, content: '' });

            if (result.success) {
                loadDirectory();
                openFile(result.path);
            } else {
                alert(result.error);
            }
        }

        async function createFolder() {
            const name = prompt('Folder name:');
            if (!name) return;

            const result = await api('mkdir', { path: currentPath + '/' + name });
            if (result.success) {
                loadDirectory();
            } else {
                alert(result.error);
            }
        }

        // Terminal with WebSocket/PTY fallback to simple terminal
        function initXterm() {
            if (term) return;

            term = new Terminal({
                cursorBlink: true,
                fontSize: <?php echo $config['terminal']['font_size'] ?? 14; ?>,
                fontFamily: "'Consolas', 'Monaco', 'Courier New', monospace",
                theme: {
                    background: '#000000',
                    foreground: '#d4d4d4',
                    cursor: '#d4d4d4',
                    cursorAccent: '#000000',
                    selection: 'rgba(255, 255, 255, 0.3)',
                    black: '#000000',
                    red: '#f44747',
                    green: '#4ec9b0',
                    yellow: '#dcdcaa',
                    blue: '#569cd6',
                    magenta: '#c586c0',
                    cyan: '#9cdcfe',
                    white: '#d4d4d4',
                }
            });

            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);

            const container = document.getElementById('terminal-container');
            term.open(container);

            term.onData(data => {
                if (terminalSocket && terminalSocket.readyState === WebSocket.OPEN) {
                    terminalSocket.send(JSON.stringify({ type: 'input', data }));
                }
            });

            window.addEventListener('resize', fitTerminal);
        }

        function fitTerminal() {
            if (terminalMode === 'websocket' && fitAddon && term) {
                try {
                    fitAddon.fit();
                    if (terminalSocket && terminalSocket.readyState === WebSocket.OPEN) {
                        terminalSocket.send(JSON.stringify({
                            type: 'resize',
                            cols: term.cols,
                            rows: term.rows
                        }));
                    }
                } catch (e) {}
            }
        }

        function tryWebSocketTerminal() {
            // Skip WebSocket if not enabled in config
            if (!TERMINAL_WEBSOCKET_ENABLED) {
                console.log('WebSocket terminal disabled in config');
                return Promise.resolve(false);
            }
            // Try proxy URL first, then direct port
            return tryWebSocketUrl(TERMINAL_WS_PROXY).then(success => {
                if (success) return true;
                console.log('Proxy WebSocket failed, trying direct port...');
                return tryWebSocketUrl(TERMINAL_WS_DIRECT);
            });
        }

        function tryWebSocketUrl(baseUrl) {
            return new Promise((resolve) => {
                const wsUrl = baseUrl + '?cwd=' + encodeURIComponent(currentPath);
                const timeout = setTimeout(() => {
                    console.log('WebSocket timeout for ' + baseUrl);
                    if (terminalSocket) {
                        terminalSocket.close();
                        terminalSocket = null;
                    }
                    resolve(false);
                }, 3000);

                try {
                    const ws = new WebSocket(wsUrl);

                    ws.onopen = () => {
                        clearTimeout(timeout);
                        console.log('WebSocket connected via ' + baseUrl);
                        terminalSocket = ws;
                        setupWebSocketHandlers(ws);
                        resolve(true);
                    };

                    ws.onerror = () => {
                        clearTimeout(timeout);
                        resolve(false);
                    };

                } catch (e) {
                    clearTimeout(timeout);
                    resolve(false);
                }
            });
        }

        function setupWebSocketHandlers(ws) {
            ws.onclose = () => {
                if (terminalMode === 'websocket') {
                    updateTerminalStatus('disconnected');
                    // Show reconnect option
                    term.writeln('\r\n\x1b[31m[Connection lost. Press Enter to reconnect]\x1b[0m');
                }
            };

            ws.onmessage = (event) => {
                try {
                    const msg = JSON.parse(event.data);
                    if (msg.type === 'output') {
                        term.write(msg.data);
                    } else if (msg.type === 'exit') {
                        term.writeln('\r\n[Process exited]');
                    }
                } catch (e) {}
            };
        }

        function enableWebSocketTerminal() {
            terminalMode = 'websocket';
            document.getElementById('terminal-container').style.display = 'block';
            document.getElementById('simple-terminal').classList.remove('active');
            updateTerminalStatus('connected');
            term.clear();
            term.focus();
            setTimeout(fitTerminal, 100);
        }

        function enableSimpleTerminal() {
            terminalMode = 'simple';
            document.getElementById('terminal-container').style.display = 'none';
            document.getElementById('simple-terminal').classList.add('active');
            document.getElementById('terminal-input').focus();
            updateTerminalStatus('simple');

            // Set up simple terminal input handler
            const input = document.getElementById('terminal-input');
            input.onkeydown = handleSimpleTerminalKey;
        }

        function showTerminalServerPrompt() {
            const output = document.getElementById('terminal-output');

            if (!TERMINAL_WEBSOCKET_ENABLED) {
                // WebSocket terminal disabled - show simple message
                output.innerHTML = `
                    <div class="terminal-server-prompt">
                        <p><strong>Basic Terminal Mode</strong></p>
                        <p>WebSocket terminal is disabled in config for security.</p>
                        <p style="margin-top:10px;font-size:12px;color:var(--text-dim)">
                            The basic terminal respects base_path restrictions.<br>
                            To enable full PTY terminal, set <code>terminal.websocket_enabled = true</code> in config.
                        </p>
                    </div>
                `;
                return;
            }

            output.innerHTML = `
                <div class="terminal-server-prompt">
                    <p><strong>Full Terminal Not Connected</strong></p>
                    <p>Interactive commands (like <code>claude</code>, <code>vim</code>, <code>htop</code>) require the PTY terminal server.</p>

                    <button class="terminal-server-btn" onclick="startTerminalServer()">▶ Start Terminal Server</button>

                    <p style="margin-top:20px;font-size:12px;color:var(--text-dim)">
                        <strong>Connection attempts:</strong><br>
                        1. WebSocket proxy (/ws/terminal) - requires web server config<br>
                        2. Direct port (:${TERMINAL_WS_DIRECT_PORT}) - requires firewall open
                    </p>

                    <p style="margin-top:15px;font-size:11px;color:var(--text-dim)">
                        Basic mode available below for simple commands (ls, cat, git, etc.)
                    </p>
                </div>
            `;
        }

        async function startTerminalServer() {
            const output = document.getElementById('terminal-output');
            output.innerHTML = '<div class="terminal-server-prompt"><p>Starting terminal server...</p></div>';

            try {
                const result = await api('terminal_start');
                if (result.success) {
                    output.innerHTML = '<div class="terminal-server-prompt"><p style="color:var(--success)">Terminal server started! Reconnecting...</p></div>';

                    // Wait a moment for server to be ready
                    await new Promise(r => setTimeout(r, 1000));

                    // Try to connect via WebSocket
                    const wsAvailable = await tryWebSocketTerminal();
                    if (wsAvailable) {
                        enableWebSocketTerminal();
                    } else {
                        output.innerHTML = `
                            <div class="terminal-server-prompt">
                                <p style="color:var(--warning)">Server started but WebSocket proxy not configured.</p>
                                <p>Add to your web server config:</p>
                                <pre style="text-align:left;background:var(--bg-dark);padding:10px;margin:10px 0;font-size:11px">
# Nginx
location /ws/terminal {
    proxy_pass http://127.0.0.1:3456;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}</pre>
                                <button class="terminal-server-btn stop" onclick="stopTerminalServer()">■ Stop Server</button>
                            </div>
                        `;
                    }
                } else {
                    throw new Error(result.error || 'Failed to start');
                }
            } catch (e) {
                output.innerHTML = `
                    <div class="terminal-server-prompt">
                        <p style="color:var(--accent-red)">Failed to start: ${escapeHtml(e.message || e.error || 'Unknown error')}</p>
                        <button class="terminal-server-btn" onclick="startTerminalServer()">▶ Retry</button>
                    </div>
                `;
            }
        }

        async function stopTerminalServer() {
            try {
                await api('terminal_stop');
                showTerminalServerPrompt();
            } catch (e) {
                alert('Failed to stop server: ' + e.message);
            }
        }

        function updateTerminalStatus(status) {
            const el = document.getElementById('terminal-status');
            if (status === 'connected') {
                el.textContent = 'PTY Connected';
                el.className = 'terminal-status connected';
            } else if (status === 'simple') {
                el.textContent = 'Basic Mode';
                el.className = 'terminal-status simple';
            } else {
                el.textContent = 'Disconnected';
                el.className = 'terminal-status disconnected';
            }
        }

        async function handleSimpleTerminalKey(e) {
            if (e.key === 'Enter') {
                const input = e.target;
                const command = input.value.trim();
                if (!command) return;

                commandHistory.push(command);
                historyIndex = commandHistory.length;
                input.value = '';

                appendTerminalOutput('$ ' + command, 'cmd-line');

                const result = await api('exec', { command, cwd: currentPath });

                if (result.output) appendTerminalOutput(result.output, 'cmd-output');
                if (result.error_output) appendTerminalOutput(result.error_output, 'cmd-error');
                if (result.cwd) currentPath = result.cwd;

            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (historyIndex > 0) {
                    historyIndex--;
                    e.target.value = commandHistory[historyIndex];
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (historyIndex < commandHistory.length - 1) {
                    historyIndex++;
                    e.target.value = commandHistory[historyIndex];
                } else {
                    historyIndex = commandHistory.length;
                    e.target.value = '';
                }
            }
        }

        function appendTerminalOutput(text, className) {
            const output = document.getElementById('terminal-output');
            const line = document.createElement('div');
            line.className = className;
            line.textContent = text;
            output.appendChild(line);
            output.scrollTop = output.scrollHeight;
        }

        async function toggleTerminal() {
            const panel = document.getElementById('terminal-panel');
            panel.classList.toggle('hidden');

            if (!panel.classList.contains('hidden')) {
                if (terminalMode) {
                    // Already initialized, just focus
                    if (terminalMode === 'websocket') {
                        term.focus();
                    } else {
                        document.getElementById('terminal-input').focus();
                    }
                    return;
                }

                // First time opening - try WebSocket, fallback to simple
                updateTerminalStatus('connecting');
                initXterm();

                const wsAvailable = await tryWebSocketTerminal();
                if (wsAvailable) {
                    enableWebSocketTerminal();
                } else {
                    enableSimpleTerminal();
                    showTerminalServerPrompt();
                }
            }
        }

        // Terminal resize functionality
        let terminalHeight = 250;
        let isResizing = false;

        function initTerminalResizer() {
            const resizer = document.getElementById('terminal-resizer');
            const panel = document.getElementById('terminal-panel');

            resizer.addEventListener('mousedown', (e) => {
                isResizing = true;
                resizer.classList.add('dragging');
                document.body.style.cursor = 'ns-resize';
                document.body.style.userSelect = 'none';
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;

                const containerRect = document.getElementById('main').getBoundingClientRect();
                const newHeight = containerRect.bottom - e.clientY;

                // Clamp height between min and max
                terminalHeight = Math.max(100, Math.min(newHeight, window.innerHeight - 100));
                panel.style.height = terminalHeight + 'px';
                panel.classList.remove('fullscreen');
                updateFullscreenIcon();

                // Refit terminal if using xterm
                fitTerminal();
            });

            document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    document.getElementById('terminal-resizer').classList.remove('dragging');
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                }
            });
        }

        function toggleTerminalFullscreen() {
            const panel = document.getElementById('terminal-panel');
            panel.classList.toggle('fullscreen');
            updateFullscreenIcon();

            // Refit terminal after animation
            setTimeout(fitTerminal, 50);
        }

        function updateFullscreenIcon() {
            const panel = document.getElementById('terminal-panel');
            const icon = document.getElementById('terminal-fullscreen-icon');
            if (panel.classList.contains('fullscreen')) {
                icon.textContent = '↓';
                icon.parentElement.title = 'Restore size (Shift+Esc)';
            } else {
                icon.textContent = '↑';
                icon.parentElement.title = 'Toggle fullscreen (Shift+Esc)';
            }
        }

        // Initialize resizer when DOM is ready
        document.addEventListener('DOMContentLoaded', initTerminalResizer);

        // Keyboard shortcut for terminal fullscreen
        document.addEventListener('keydown', (e) => {
            if (e.shiftKey && e.key === 'Escape') {
                const panel = document.getElementById('terminal-panel');
                if (!panel.classList.contains('hidden')) {
                    toggleTerminalFullscreen();
                }
            }
        });

        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
        }

        // Quick Open (file search)
        async function openQuickOpen() {
            const query = prompt('Quick Open - Enter filename to search:');
            if (!query || !query.trim()) return;

            const result = await api('search', { query: query.trim(), path: '.' });
            if (result.success && result.results && result.results.length > 0) {
                // Get unique files
                const files = [...new Set(result.results.map(r => r.file))].slice(0, 10);

                if (files.length === 1) {
                    openFile(files[0]);
                } else {
                    // Show selection
                    const list = files.map((f, i) => `${i + 1}. ${f.replace(currentPath, '')}`).join('\n');
                    const choice = prompt(`Found ${files.length} files:\n\n${list}\n\nEnter number to open:`);
                    if (choice) {
                        const idx = parseInt(choice) - 1;
                        if (idx >= 0 && idx < files.length) {
                            openFile(files[idx]);
                        }
                    }
                }
            } else {
                alert('No files found matching: ' + query);
            }
        }

        // AI
        function toggleAI() {
            document.getElementById('ai-panel').classList.toggle('active');
        }

        async function updateAIStatus() {
            const result = await api('ai_status');
            const el = document.getElementById('ai-status');

            if (result.is_pro) {
                el.textContent = 'AI: Pro (Unlimited)';
                el.className = 'ai-status pro';
            } else {
                const remaining = result.rate_limit?.remaining ?? 0;
                el.textContent = `AI: Free (${remaining}/20 today)`;
                el.className = 'ai-status';
            }
        }

        async function aiSend() {
            const input = document.getElementById('ai-input');
            const message = input.value.trim();
            if (!message) return;

            const contextCode = activeTab ? editor.getModel().getValueInRange(editor.getSelection()) || '' : '';
            const contextFile = activeTab?.path || '';

            appendAIMessage(message, 'user');
            input.value = '';

            const result = await api('ai_chat', { message, context_code: contextCode, context_file: contextFile });

            if (result.success) {
                appendAIMessage(result.content, 'assistant');
            } else {
                appendAIMessage('Error: ' + result.error, 'assistant');
            }

            updateAIStatus();
        }

        async function aiExplain() {
            if (!activeTab) return;
            const code = editor.getModel().getValueInRange(editor.getSelection()) || editor.getValue();
            appendAIMessage('Explain this code', 'user');
            const result = await api('ai_explain', { code, file: activeTab.path });
            appendAIMessage(result.success ? result.content : result.error, 'assistant');
            updateAIStatus();
        }

        async function aiImprove() {
            if (!activeTab) return;
            const code = editor.getModel().getValueInRange(editor.getSelection()) || editor.getValue();
            appendAIMessage('Suggest improvements', 'user');
            const result = await api('ai_improve', { code, file: activeTab.path });
            appendAIMessage(result.success ? result.content : result.error, 'assistant');
            updateAIStatus();
        }

        async function aiTests() {
            if (!activeTab) return;
            const code = editor.getModel().getValueInRange(editor.getSelection()) || editor.getValue();
            appendAIMessage('Generate tests', 'user');
            const result = await api('ai_tests', { code, file: activeTab.path });
            appendAIMessage(result.success ? result.content : result.error, 'assistant');
            updateAIStatus();
        }

        function appendAIMessage(content, role) {
            const container = document.getElementById('ai-messages');
            const div = document.createElement('div');
            div.className = 'ai-message ' + role;

            // Simple markdown-ish rendering
            let html = escapeHtml(content);
            html = html.replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>');
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
            html = html.replace(/\n/g, '<br>');

            div.innerHTML = html;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        // Git Panel
        let gitStatus = null;

        function toggleGit() {
            const panel = document.getElementById('git-panel');
            panel.classList.toggle('active');
            if (panel.classList.contains('active')) {
                refreshGitStatus();
            }
        }

        async function refreshGitStatus() {
            const content = document.getElementById('git-content');
            content.innerHTML = '<div class="git-empty">Loading...</div>';

            try {
                gitStatus = await api('git_status');

                if (!gitStatus.is_repo) {
                    content.innerHTML = `
                        <div class="git-not-repo">
                            <p>Not a Git repository</p>
                            <button class="git-btn" onclick="gitInit()">Initialize Repository</button>
                        </div>
                    `;
                    document.getElementById('git-branch-name').textContent = '-';
                    document.getElementById('git-sync-status').textContent = '';
                    return;
                }

                // Update branch name
                document.getElementById('git-branch-name').textContent = gitStatus.branch || 'HEAD';

                // Update sync status
                const syncEl = document.getElementById('git-sync-status');
                if (gitStatus.ahead > 0 && gitStatus.behind > 0) {
                    syncEl.textContent = `↑${gitStatus.ahead} ↓${gitStatus.behind}`;
                    syncEl.className = 'git-sync-status';
                } else if (gitStatus.ahead > 0) {
                    syncEl.textContent = `↑${gitStatus.ahead}`;
                    syncEl.className = 'git-sync-status ahead';
                } else if (gitStatus.behind > 0) {
                    syncEl.textContent = `↓${gitStatus.behind}`;
                    syncEl.className = 'git-sync-status behind';
                } else {
                    syncEl.textContent = '';
                }

                // Render file lists
                let html = '';

                // Staged files
                if (gitStatus.staged.length > 0) {
                    html += `
                        <div class="git-section">
                            <div class="git-section-header">
                                <span>Staged Changes (${gitStatus.staged.length})</span>
                                <div class="git-section-actions">
                                    <button class="git-section-btn" onclick="gitUnstageAll()" title="Unstage all">−</button>
                                </div>
                            </div>
                            ${gitStatus.staged.map(f => gitFileHtml(f, true)).join('')}
                        </div>
                    `;
                }

                // Unstaged files
                if (gitStatus.unstaged.length > 0) {
                    html += `
                        <div class="git-section">
                            <div class="git-section-header">
                                <span>Changes (${gitStatus.unstaged.length})</span>
                                <div class="git-section-actions">
                                    <button class="git-section-btn" onclick="gitStageAll()" title="Stage all">+</button>
                                </div>
                            </div>
                            ${gitStatus.unstaged.map(f => gitFileHtml(f, false)).join('')}
                        </div>
                    `;
                }

                // Untracked files
                if (gitStatus.untracked.length > 0) {
                    html += `
                        <div class="git-section">
                            <div class="git-section-header">
                                <span>Untracked (${gitStatus.untracked.length})</span>
                                <div class="git-section-actions">
                                    <button class="git-section-btn" onclick="gitStageAll()" title="Stage all">+</button>
                                </div>
                            </div>
                            ${gitStatus.untracked.map(f => gitFileHtml(f, false)).join('')}
                        </div>
                    `;
                }

                if (!html) {
                    html = '<div class="git-empty">No changes</div>';
                }

                content.innerHTML = html;

                // Update commit button state
                document.getElementById('git-commit-btn').disabled = gitStatus.staged.length === 0;

            } catch (e) {
                content.innerHTML = `<div class="git-empty">Error: ${e.message}</div>`;
            }
        }

        function gitFileHtml(file, isStaged) {
            const status = file.status;
            const statusLabel = {
                'M': 'M', 'A': 'A', 'D': 'D', 'R': 'R', 'new': '?'
            }[status] || status;

            const escapedFile = escapeHtml(file.file).replace(/'/g, "\\'");

            return `
                <div class="git-file" onclick="openFile('${escapedFile}')">
                    <span class="git-file-status ${status}">${statusLabel}</span>
                    <span class="git-file-name" title="${escapeHtml(file.file)}">${escapeHtml(file.file)}</span>
                    <div class="git-file-actions">
                        ${isStaged
                            ? `<button class="git-file-btn" onclick="event.stopPropagation(); gitUnstage('${escapedFile}')" title="Unstage">−</button>`
                            : `<button class="git-file-btn" onclick="event.stopPropagation(); gitStage('${escapedFile}')" title="Stage">+</button>
                               <button class="git-file-btn danger" onclick="event.stopPropagation(); gitDiscard('${escapedFile}')" title="Discard changes">✕</button>`
                        }
                    </div>
                </div>
            `;
        }

        async function gitStage(file) {
            await api('git_stage', { file });
            refreshGitStatus();
        }

        async function gitUnstage(file) {
            await api('git_unstage', { file });
            refreshGitStatus();
        }

        async function gitStageAll() {
            await api('git_stage_all');
            refreshGitStatus();
        }

        async function gitUnstageAll() {
            await api('git_unstage_all');
            refreshGitStatus();
        }

        async function gitDiscard(file) {
            if (!confirm(`Discard changes to ${file}? This cannot be undone.`)) return;
            await api('git_discard', { file });
            refreshGitStatus();
            // Reload file if it's open
            const tab = openTabs.find(t => t.path.endsWith(file));
            if (tab) {
                const result = await api('read', { path: tab.path });
                if (result.success) {
                    tab.content = result.content;
                    tab.unsaved = false;
                    if (tab === activeTab) {
                        editor.setValue(tab.content);
                    }
                    renderTabs();
                }
            }
        }

        async function gitCommit() {
            const message = document.getElementById('git-commit-message').value.trim();
            if (!message) {
                alert('Please enter a commit message');
                return;
            }

            const result = await api('git_commit', { message });
            if (result.success) {
                document.getElementById('git-commit-message').value = '';
                refreshGitStatus();
            } else {
                alert('Commit failed:\n' + (result.output || result.error));
            }
        }

        async function gitPush() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Pushing...';

            try {
                const result = await api('git_push');
                if (result.output && result.output.includes('error')) {
                    alert('Push result:\n' + result.output);
                }
                refreshGitStatus();
            } finally {
                btn.disabled = false;
                btn.textContent = '↓ Pull';
            }
        }

        async function gitPull() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Pulling...';

            try {
                const result = await api('git_pull');
                if (result.output) {
                    // Refresh open files that might have changed
                    for (const tab of openTabs) {
                        const fileResult = await api('read', { path: tab.path });
                        if (fileResult.success && fileResult.content !== tab.content) {
                            tab.content = fileResult.content;
                            if (tab === activeTab) {
                                editor.setValue(tab.content);
                            }
                        }
                    }
                }
                refreshGitStatus();
            } finally {
                btn.disabled = false;
                btn.textContent = '↑ Push';
            }
        }

        async function gitInit() {
            const result = await api('exec', { command: 'git init' });
            if (result.success) {
                refreshGitStatus();
            } else {
                alert('Failed to initialize repository:\n' + result.error_output);
            }
        }

        // Helpers
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
