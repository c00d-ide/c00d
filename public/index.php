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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' shape-rendering='crispEdges'><rect x='4' y='2' width='8' height='12' fill='%23ff0000'/><rect x='6' y='4' width='4' height='8' fill='%231e1e1e'/></svg>">
    <style>
        @font-face {
            font-family: 'Tiny5';
            src: url('fonts/Tiny5-Regular.ttf') format('truetype');
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-dark: #1e1e1e;
            --bg-darker: #252526;
            --bg-sidebar: #252526;
            --border: #3c3c3c;
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
        #monaco-container {
            flex: 1;
            position: relative;
            display: flex;
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
            height: 200px;
            background: var(--bg-dark);
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        #terminal-panel.hidden { display: none; }

        .terminal-header {
            padding: 5px 15px;
            background: var(--bg-darker);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        #terminal-output {
            flex: 1;
            overflow-y: auto;
            padding: 10px 15px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: <?php echo $config['terminal']['font_size'] ?? 14; ?>px;
            white-space: pre-wrap;
            line-height: 1.4;
        }

        .cmd-line { color: var(--success); }
        .cmd-output { color: var(--text); }
        .cmd-error { color: var(--accent-red); }

        #terminal-input-line {
            display: flex;
            padding: 8px 15px;
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
            font-size: <?php echo $config['terminal']['font_size'] ?? 14; ?>px;
            outline: none;
        }

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
        }

        #welcome h2 { color: var(--accent); margin-bottom: 15px; }
        #welcome p { margin-bottom: 10px; }
        #welcome kbd {
            background: var(--bg-darker);
            padding: 3px 8px;
            border-radius: 3px;
            font-family: monospace;
        }

        /* Update Banner */
        #update-banner {
            display: none;
            padding: 8px 15px;
            background: linear-gradient(90deg, #b8860b, #daa520);
            color: #1e1e1e;
            font-size: 13px;
            font-weight: 500;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        #update-banner.visible {
            display: flex;
        }

        #update-banner .update-text {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #update-banner .update-icon {
            font-size: 16px;
        }

        #update-banner .update-btn {
            background: #1e1e1e;
            color: #daa520;
            border: none;
            padding: 5px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
        }

        #update-banner .update-btn:hover {
            background: #2d2d2d;
        }

        #update-banner .update-btn.primary {
            background: #ff0000;
            color: white;
        }

        #update-banner .update-btn.primary:hover {
            background: #cc0000;
        }

        #update-banner .dismiss-btn {
            background: transparent;
            border: none;
            color: #1e1e1e;
            font-size: 18px;
            cursor: pointer;
            padding: 0 5px;
            opacity: 0.7;
        }

        #update-banner .dismiss-btn:hover {
            opacity: 1;
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
                    <rect x="2" y="2" width="12" height="8" fill="#1e1e1e"/>
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
                        <rect x="2" y="2" width="12" height="8" fill="#1e1e1e"/>
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
                <button class="toolbar-btn" onclick="toggleTerminal()">Terminal</button>
                <button class="toolbar-btn" onclick="toggleAI()">AI Chat</button>
                <button class="toolbar-btn" onclick="saveFile()">Save</button>
            </div>
        </div>

        <!-- Update Banner -->
        <div id="update-banner">
            <span class="update-text">
                <span class="update-icon">&#9733;</span>
                <span id="update-message">Update available: v<span id="update-version"></span></span>
            </span>
            <button class="update-btn primary" onclick="performUpdate()">Update Now</button>
            <button class="update-btn" onclick="window.open('https://c00d.com/download', '_blank')">Download</button>
            <button class="dismiss-btn" onclick="dismissUpdate()" title="Dismiss for 7 days">&times;</button>
        </div>

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

            <!-- Editor Area -->
            <div id="editor-area">
                <div id="tabs"></div>
                <div id="monaco-container">
                    <div id="welcome">
                        <div>
                            <svg width="80" height="80" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges" style="margin-bottom: 20px;">
                                <rect x="1" y="1" width="14" height="12"/>
                                <rect x="2" y="2" width="12" height="8" fill="#1e1e1e"/>
                                <rect x="3" y="3" width="2" height="1" fill="#ff0000"/>
                                <rect x="6" y="3" width="4" height="1" fill="#ff0000"/>
                                <rect x="3" y="5" width="3" height="1" fill="#4ec9b0"/>
                                <rect x="7" y="5" width="5" height="1" fill="#dcdcaa"/>
                                <rect x="5" y="13" width="6" height="2"/>
                            </svg>
                            <h2>Welcome to c00d IDE</h2>
                            <p>Select a file from the sidebar to start editing</p>
                            <p><kbd>Ctrl+S</kbd> Save &nbsp; <kbd>Ctrl+P</kbd> Quick Open &nbsp; <kbd>Ctrl+`</kbd> Terminal</p>
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
            <div class="terminal-header">
                <span>Terminal</span>
                <button class="sidebar-btn" onclick="toggleTerminal()">×</button>
            </div>
            <div id="terminal-output"></div>
            <div id="terminal-input-line">
                <span id="terminal-prompt">$</span>
                <input type="text" id="terminal-input" placeholder="Type a command...">
            </div>
        </div>
    </div>

    <!-- Monaco Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
    <script>
        // State
        let editor = null;
        let openTabs = [];
        let activeTab = null;
        let currentPath = '/';
        let commandHistory = [];
        let historyIndex = -1;

        // Config
        const editorConfig = <?php echo json_encode($editorConfig); ?>;

        // Pixel art icons
        const folderIcon = `<svg width="16" height="16" viewBox="0 0 16 16" fill="#dcdcaa" shape-rendering="crispEdges">
            <rect x="1" y="3" width="6" height="2"/>
            <rect x="1" y="5" width="14" height="10"/>
            <rect x="2" y="6" width="12" height="8" fill="#252526"/>
        </svg>`;

        const fileIcon = `<svg width="16" height="16" viewBox="0 0 16 16" fill="#d4d4d4" shape-rendering="crispEdges">
            <rect x="2" y="1" width="9" height="14"/>
            <rect x="11" y="1" width="3" height="3"/>
            <rect x="11" y="4" width="3" height="11"/>
            <rect x="3" y="2" width="7" height="12" fill="#252526"/>
            <rect x="4" y="4" width="5" height="1" fill="#808080"/>
            <rect x="4" y="6" width="6" height="1" fill="#808080"/>
            <rect x="4" y="8" width="4" height="1" fill="#808080"/>
        </svg>`;

        const c00dLogo = `<svg width="24" height="24" viewBox="0 0 16 16" fill="#ff0000" shape-rendering="crispEdges">
            <rect x="1" y="1" width="14" height="12"/>
            <rect x="2" y="2" width="12" height="8" fill="#1e1e1e"/>
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
                editor = monaco.editor.create(document.getElementById('monaco-container'), {
                    value: '',
                    language: 'plaintext',
                    theme: editorConfig.theme || 'vs-dark',
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

                // Keyboard shortcuts
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, saveFile);
            });

            // Load directory
            loadDirectory();

            // Terminal input
            document.getElementById('terminal-input').addEventListener('keydown', handleTerminalKey);

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

            // Global shortcuts
            document.addEventListener('keydown', e => {
                if (e.ctrlKey && e.key === '`') {
                    e.preventDefault();
                    toggleTerminal();
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
            document.getElementById('update-version').textContent = version;
            document.getElementById('update-banner').classList.add('visible');
        }

        function hideUpdateBanner() {
            document.getElementById('update-banner').classList.remove('visible');
        }

        function dismissUpdate() {
            // Dismiss for 7 days
            const sevenDays = 7 * 24 * 60 * 60 * 1000;
            localStorage.setItem('c00d_update_dismissed_until', (Date.now() + sevenDays).toString());
            hideUpdateBanner();
        }

        async function performUpdate() {
            if (!updateInfo) return;

            const confirmMsg = `Update c00d IDE to version ${updateInfo.remote_version}?\n\n` +
                `Current version: ${updateInfo.local_version}\n` +
                `New version: ${updateInfo.remote_version}\n\n` +
                `A backup will be created before updating.\n` +
                `Your config.local.php and data/ will be preserved.`;

            if (!confirm(confirmMsg)) return;

            // Show updating message
            const banner = document.getElementById('update-banner');
            banner.innerHTML = '<span class="update-text"><span class="update-icon">&#8987;</span> Updating... Please wait...</span>';

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
                    // Restore banner
                    banner.innerHTML = `
                        <span class="update-text">
                            <span class="update-icon">&#9733;</span>
                            <span id="update-message">Update available: v<span id="update-version">${updateInfo.remote_version}</span></span>
                        </span>
                        <button class="update-btn primary" onclick="performUpdate()">Update Now</button>
                        <button class="update-btn" onclick="window.open('https://c00d.com/download', '_blank')">Download</button>
                        <button class="dismiss-btn" onclick="dismissUpdate()" title="Dismiss for 7 days">&times;</button>
                    `;
                }
            } catch (e) {
                alert('Update failed: ' + e.message);
                // Restore banner
                banner.innerHTML = `
                    <span class="update-text">
                        <span class="update-icon">&#9733;</span>
                        <span id="update-message">Update available: v<span id="update-version">${updateInfo.remote_version}</span></span>
                    </span>
                    <button class="update-btn primary" onclick="performUpdate()">Update Now</button>
                    <button class="update-btn" onclick="window.open('https://c00d.com/download', '_blank')">Download</button>
                    <button class="dismiss-btn" onclick="dismissUpdate()" title="Dismiss for 7 days">&times;</button>
                `;
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

        // Terminal
        function toggleTerminal() {
            document.getElementById('terminal-panel').classList.toggle('hidden');
            if (!document.getElementById('terminal-panel').classList.contains('hidden')) {
                document.getElementById('terminal-input').focus();
            }
        }

        async function handleTerminalKey(e) {
            if (e.key === 'Enter') {
                const input = e.target;
                const command = input.value.trim();
                if (!command) return;

                commandHistory.push(command);
                historyIndex = commandHistory.length;
                input.value = '';

                appendTerminal('$ ' + command, 'cmd-line');

                const result = await api('exec', { command, cwd: currentPath });

                if (result.output) appendTerminal(result.output, 'cmd-output');
                if (result.error_output) appendTerminal(result.error_output, 'cmd-error');

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

        function appendTerminal(text, className) {
            const output = document.getElementById('terminal-output');
            const line = document.createElement('div');
            line.className = className;
            line.textContent = text;
            output.appendChild(line);
            output.scrollTop = output.scrollHeight;
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

        // Helpers
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
