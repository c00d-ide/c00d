<?php
/**
 * c00d IDE - API Endpoint
 * Handles all AJAX requests from the frontend
 */

header('Content-Type: application/json');

// Load autoloader and config
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/IDE.php';
require_once __DIR__ . '/../src/AI.php';

// Load config
$configFile = file_exists(__DIR__ . '/../config.local.php')
    ? __DIR__ . '/../config.local.php'
    : __DIR__ . '/../config.php';
$config = require $configFile;

// Session handling - configure before starting
$sessionLifetime = $config['session_lifetime'] ?? 86400; // 24 hours default
$dataDir = __DIR__ . '/../data';

// Ensure data directory exists for sessions
$sessionDir = $dataDir . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0755, true);
}

// Configure session
ini_set('session.save_path', $sessionDir);
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

// Use HTTPS-only cookies if on HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_name('c00d_session');
session_start();

// Security: Check password
if (!empty($config['password'])) {
    if (!isset($_SESSION['c00d_authenticated']) || $_SESSION['c00d_authenticated'] !== true) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
            if ($_POST['password'] === $config['password']) {
                $_SESSION['c00d_authenticated'] = true;
                echo json_encode(['success' => true]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid password']);
                exit;
            }
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required', 'needs_auth' => true]);
        exit;
    }
}

// Security: Check allowed IPs
if (!empty($config['security']['allowed_ips'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'];
    if (!in_array($clientIp, $config['security']['allowed_ips'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
}

// Initialize components
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$db = C00d\Database::getInstance($dataDir);
$ide = new C00d\IDE($db, [
    'base_path' => $config['base_path'],
    'hidden_files' => $config['files']['show_hidden'] ?? true,
    'denied_paths' => $config['files']['denied_paths'] ?? [],
]);
$ai = new C00d\AI($db, $config['ai'] ?? []);

// Handle request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $result = match($action) {
        // File operations
        'list' => $ide->listDirectory($_POST['path'] ?? '.'),
        'read' => $ide->readFile($_POST['path'] ?? ''),
        'write' => $ide->writeFile(
            $_POST['path'] ?? '',
            $_POST['content'] ?? '',
            isset($_POST['base64']) && $_POST['base64'] === '1'
        ),
        'mkdir' => $ide->createDirectory($_POST['path'] ?? ''),
        'delete' => $ide->delete($_POST['path'] ?? ''),
        'rename' => $ide->rename($_POST['old_path'] ?? '', $_POST['new_path'] ?? ''),
        'search' => $ide->searchFiles($_POST['query'] ?? '', $_POST['path'] ?? '.'),

        // Terminal
        'exec' => $ide->executeCommand($_POST['command'] ?? '', $_POST['cwd'] ?? null),
        'history' => ['history' => $ide->getCommandHistory()],

        // AI
        'ai_chat' => $ai->chat(
            $_POST['message'] ?? '',
            $_POST['context_code'] ?? '',
            $_POST['context_file'] ?? ''
        ),
        'ai_explain' => $ai->explainCode($_POST['code'] ?? '', $_POST['file'] ?? ''),
        'ai_fix' => $ai->fixCode($_POST['code'] ?? '', $_POST['error'] ?? '', $_POST['file'] ?? ''),
        'ai_tests' => $ai->generateTests($_POST['code'] ?? '', $_POST['file'] ?? ''),
        'ai_improve' => $ai->improveCode($_POST['code'] ?? '', $_POST['file'] ?? ''),
        'ai_commit' => $ai->generateCommitMessage($_POST['diff'] ?? ''),
        'ai_status' => [
            'rate_limit' => $ai->checkRateLimit(),
            'is_pro' => $ai->isProLicense(),
            'history' => $db->getAiHistory(20),
        ],
        'ai_clear_history' => (function() use ($db) {
            $db->clearAiHistory();
            return ['success' => true];
        })(),

        // Editor state
        'get_tabs' => ['tabs' => $ide->getOpenTabs()],
        'save_tab' => (function() use ($ide) {
            $ide->saveTabState(
                $_POST['path'] ?? '',
                intval($_POST['line'] ?? 1),
                intval($_POST['col'] ?? 1),
                isset($_POST['active']) && $_POST['active'] === '1'
            );
            return ['success' => true];
        })(),
        'close_tab' => (function() use ($ide) {
            $ide->closeTabState($_POST['path'] ?? '');
            return ['success' => true];
        })(),

        // Settings
        'get_setting' => ['value' => $ide->getSetting($_POST['key'] ?? '', $_POST['default'] ?? null)],
        'set_setting' => (function() use ($ide) {
            $ide->setSetting($_POST['key'] ?? '', $_POST['value'] ?? '');
            return ['success' => true];
        })(),
        'get_settings' => [
            'editor' => $config['editor'] ?? [],
            'terminal' => $config['terminal'] ?? [],
        ],

        // Recent files
        'recent_files' => ['files' => $ide->getRecentFiles()],

        // Git operations
        'git_status' => (function() use ($ide) {
            $basePath = $ide->getBasePath();

            // Check if git repo
            if (!is_dir($basePath . '/.git')) {
                return ['is_repo' => false];
            }

            // Get current branch
            $branch = trim(shell_exec("cd " . escapeshellarg($basePath) . " && git branch --show-current 2>/dev/null") ?: '');

            // Get status (porcelain for parsing)
            $statusOutput = shell_exec("cd " . escapeshellarg($basePath) . " && git status --porcelain 2>/dev/null") ?: '';

            $staged = [];
            $unstaged = [];
            $untracked = [];

            foreach (explode("\n", trim($statusOutput)) as $line) {
                if (empty($line)) continue;

                $index = substr($line, 0, 1);
                $worktree = substr($line, 1, 1);
                $file = trim(substr($line, 3));

                if ($index === '?' && $worktree === '?') {
                    $untracked[] = ['file' => $file, 'status' => 'new'];
                } else {
                    if ($index !== ' ' && $index !== '?') {
                        $staged[] = ['file' => $file, 'status' => $index];
                    }
                    if ($worktree !== ' ' && $worktree !== '?') {
                        $unstaged[] = ['file' => $file, 'status' => $worktree];
                    }
                }
            }

            // Get remote status
            $ahead = 0;
            $behind = 0;
            $trackingOutput = shell_exec("cd " . escapeshellarg($basePath) . " && git rev-list --left-right --count HEAD...@{upstream} 2>/dev/null") ?: '';
            if (preg_match('/(\d+)\s+(\d+)/', $trackingOutput, $m)) {
                $ahead = intval($m[1]);
                $behind = intval($m[2]);
            }

            // Get remote URL
            $remoteUrl = trim(shell_exec("cd " . escapeshellarg($basePath) . " && git remote get-url origin 2>/dev/null") ?: '');

            return [
                'is_repo' => true,
                'branch' => $branch,
                'staged' => $staged,
                'unstaged' => $unstaged,
                'untracked' => $untracked,
                'ahead' => $ahead,
                'behind' => $behind,
                'remote_url' => $remoteUrl,
            ];
        })(),

        'git_stage' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $file = $_POST['file'] ?? '';
            if (empty($file)) {
                throw new Exception('No file specified');
            }
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git add " . escapeshellarg($file) . " 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_unstage' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $file = $_POST['file'] ?? '';
            if (empty($file)) {
                throw new Exception('No file specified');
            }
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git restore --staged " . escapeshellarg($file) . " 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_stage_all' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git add -A 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_unstage_all' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git restore --staged . 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_commit' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $message = $_POST['message'] ?? '';
            if (empty($message)) {
                throw new Exception('Commit message required');
            }
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git commit -m " . escapeshellarg($message) . " 2>&1");
            $exitCode = 0;
            exec("cd " . escapeshellarg($basePath) . " && git rev-parse HEAD 2>/dev/null", $dummy, $exitCode);
            return ['success' => $exitCode === 0, 'output' => $output];
        })(),

        'git_push' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git push 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_pull' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git pull 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        'git_diff' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $file = $_POST['file'] ?? '';
            $staged = isset($_POST['staged']) && $_POST['staged'] === '1';

            $cmd = "cd " . escapeshellarg($basePath) . " && git diff ";
            if ($staged) $cmd .= "--staged ";
            if (!empty($file)) $cmd .= escapeshellarg($file);
            $cmd .= " 2>&1";

            $output = shell_exec($cmd);
            return ['success' => true, 'diff' => $output];
        })(),

        'git_discard' => (function() use ($ide) {
            $basePath = $ide->getBasePath();
            $file = $_POST['file'] ?? '';
            if (empty($file)) {
                throw new Exception('No file specified');
            }
            $output = shell_exec("cd " . escapeshellarg($basePath) . " && git checkout -- " . escapeshellarg($file) . " 2>&1");
            return ['success' => true, 'output' => $output];
        })(),

        // Terminal server management
        'terminal_status' => (function() use ($config) {
            $websocketEnabled = $config['terminal']['websocket_enabled'] ?? false;
            $pidFile = __DIR__ . '/../data/terminal.pid';
            $running = false;
            $pid = null;

            if (file_exists($pidFile)) {
                $pid = intval(trim(file_get_contents($pidFile)));
                // Check if process is actually running
                if ($pid > 0 && file_exists("/proc/$pid")) {
                    $running = true;
                } else {
                    // Stale PID file
                    @unlink($pidFile);
                    $pid = null;
                }
            }

            return [
                'enabled' => $websocketEnabled,
                'running' => $running,
                'pid' => $pid,
            ];
        })(),

        'terminal_start' => (function() use ($config) {
            // Check if WebSocket terminal is enabled
            if (!($config['terminal']['websocket_enabled'] ?? false)) {
                throw new Exception('WebSocket terminal is disabled in config. Set terminal.websocket_enabled to true to enable.');
            }

            $terminalDir = __DIR__ . '/../terminal';
            $pidFile = __DIR__ . '/../data/terminal.pid';
            $logFile = __DIR__ . '/../data/terminal.log';
            $port = $config['terminal']['server_port'] ?? 3456;

            // Check if already running
            if (file_exists($pidFile)) {
                $pid = intval(trim(file_get_contents($pidFile)));
                if ($pid > 0 && file_exists("/proc/$pid")) {
                    return ['success' => true, 'message' => 'Already running', 'pid' => $pid];
                }
            }

            // Check if node is available
            $nodePath = trim(shell_exec('which node 2>/dev/null') ?: '');
            if (empty($nodePath)) {
                throw new Exception('Node.js not found. Please install Node.js first.');
            }

            // Check if server.js exists
            if (!file_exists("$terminalDir/server.js")) {
                throw new Exception('Terminal server not found at ' . $terminalDir);
            }

            // Check if node_modules exists
            if (!is_dir("$terminalDir/node_modules")) {
                throw new Exception('Dependencies not installed. Run: cd terminal && npm install');
            }

            // Start the server in background with configured port
            $cmd = "cd " . escapeshellarg($terminalDir) . " && TERMINAL_PORT=$port nohup $nodePath server.js > " . escapeshellarg($logFile) . " 2>&1 & echo $!";
            $pid = intval(trim(shell_exec($cmd)));

            if ($pid > 0) {
                // Wait a moment and verify it started
                usleep(500000); // 0.5 seconds

                if (file_exists("/proc/$pid")) {
                    file_put_contents($pidFile, $pid);
                    return ['success' => true, 'pid' => $pid, 'message' => 'Terminal server started'];
                } else {
                    // Check log for errors
                    $log = file_exists($logFile) ? trim(shell_exec("tail -5 " . escapeshellarg($logFile))) : '';
                    throw new Exception('Server failed to start. ' . ($log ? "Log: $log" : ''));
                }
            }

            throw new Exception('Failed to start terminal server');
        })(),

        'terminal_stop' => (function() {
            $pidFile = __DIR__ . '/../data/terminal.pid';

            if (!file_exists($pidFile)) {
                return ['success' => true, 'message' => 'Not running'];
            }

            $pid = intval(trim(file_get_contents($pidFile)));

            if ($pid > 0) {
                // Send SIGTERM
                posix_kill($pid, 15);

                // Wait a moment
                usleep(500000);

                // Force kill if still running
                if (file_exists("/proc/$pid")) {
                    posix_kill($pid, 9);
                }
            }

            @unlink($pidFile);

            return ['success' => true, 'message' => 'Terminal server stopped'];
        })(),

        // Info
        'info' => [
            'base_path' => $ide->getBasePath(),
            'php_version' => PHP_VERSION,
            'ai_provider' => $config['ai']['provider'] ?? 'c00d',
            'is_pro' => $ai->isProLicense(),
        ],

        // Login (if already authenticated, just return success)
        'login' => ['success' => true, 'already_authenticated' => true],

        // Logout
        'logout' => (function() {
            session_destroy();
            return ['success' => true];
        })(),

        // Update system
        'check_update' => (function() {
            // Read local version
            $versionFile = __DIR__ . '/../VERSION';
            $localVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';

            // Fetch remote version
            $ctx = stream_context_create([
                'http' => ['timeout' => 5],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $remoteData = @file_get_contents('https://c00d.com/api/version.php', false, $ctx);

            if ($remoteData === false) {
                return [
                    'local_version' => $localVersion,
                    'update_available' => false,
                    'error' => 'Could not check for updates'
                ];
            }

            $remote = json_decode($remoteData, true);
            if (!$remote || !isset($remote['version'])) {
                return [
                    'local_version' => $localVersion,
                    'update_available' => false,
                    'error' => 'Invalid update response'
                ];
            }

            // Compare versions
            $updateAvailable = version_compare($remote['version'], $localVersion, '>');

            return [
                'local_version' => $localVersion,
                'remote_version' => $remote['version'],
                'update_available' => $updateAvailable,
                'changelog' => $remote['changelog'] ?? '',
                'download_url' => $remote['download_url'] ?? '',
                'release_date' => $remote['release_date'] ?? '',
                'min_php' => $remote['min_php'] ?? '8.0',
            ];
        })(),

        'perform_update' => (function() use ($dataDir) {
            $result = ['success' => false];

            // Create backups directory
            $backupDir = $dataDir . '/backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Create timestamped backup
            $timestamp = date('Y-m-d_His');
            $backupPath = $backupDir . '/backup-' . $timestamp;
            mkdir($backupPath, 0755, true);

            $rootDir = dirname(__DIR__);

            // Files/dirs to backup (exclude data and config.local.php)
            $itemsToBackup = ['public', 'src', 'config.php', 'VERSION'];

            foreach ($itemsToBackup as $item) {
                $source = $rootDir . '/' . $item;
                $dest = $backupPath . '/' . $item;

                if (is_dir($source)) {
                    // Recursive directory copy
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $file) {
                        $targetPath = $dest . '/' . $iterator->getSubPathname();
                        if ($file->isDir()) {
                            @mkdir($targetPath, 0755, true);
                        } else {
                            @mkdir(dirname($targetPath), 0755, true);
                            @copy($file->getPathname(), $targetPath);
                        }
                    }
                } elseif (file_exists($source)) {
                    @copy($source, $dest);
                }
            }

            // Download update zip
            $ctx = stream_context_create([
                'http' => ['timeout' => 60],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $zipUrl = 'https://c00d.com/download?format=zip';
            $zipPath = $dataDir . '/update-' . $timestamp . '.zip';
            $zipContent = @file_get_contents($zipUrl, false, $ctx);

            if ($zipContent === false) {
                return ['success' => false, 'error' => 'Failed to download update'];
            }

            file_put_contents($zipPath, $zipContent);

            // Extract zip
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                @unlink($zipPath);
                return ['success' => false, 'error' => 'Failed to open update archive'];
            }

            // Extract to temp directory
            $extractPath = $dataDir . '/update-extract-' . $timestamp;
            mkdir($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();

            // Find the root of extracted files (might be in a subdirectory)
            $extractedRoot = $extractPath;
            $entries = scandir($extractPath);
            $entries = array_diff($entries, ['.', '..']);
            if (count($entries) === 1 && is_dir($extractPath . '/' . reset($entries))) {
                $extractedRoot = $extractPath . '/' . reset($entries);
            }

            // Copy updated files (preserve config.local.php and data/)
            $filesToUpdate = ['public', 'src', 'config.php', 'VERSION'];

            foreach ($filesToUpdate as $item) {
                $source = $extractedRoot . '/' . $item;
                $dest = $rootDir . '/' . $item;

                if (!file_exists($source)) continue;

                if (is_dir($source)) {
                    // Recursive copy
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($iterator as $file) {
                        $targetPath = $dest . '/' . $iterator->getSubPathname();
                        if ($file->isDir()) {
                            @mkdir($targetPath, 0755, true);
                        } else {
                            @mkdir(dirname($targetPath), 0755, true);
                            @copy($file->getPathname(), $targetPath);
                        }
                    }
                } else {
                    @copy($source, $dest);
                }
            }

            // Cleanup temp files
            // Recursive delete helper
            $deleteDir = function($dir) use (&$deleteDir) {
                if (!is_dir($dir)) return;
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $dir . '/' . $item;
                    is_dir($path) ? $deleteDir($path) : unlink($path);
                }
                rmdir($dir);
            };

            $deleteDir($extractPath);
            @unlink($zipPath);

            // Read new version
            $newVersionFile = $rootDir . '/VERSION';
            $newVersion = file_exists($newVersionFile) ? trim(file_get_contents($newVersionFile)) : 'unknown';

            return [
                'success' => true,
                'message' => 'Update completed successfully',
                'new_version' => $newVersion,
                'backup_path' => $backupPath
            ];
        })(),

        default => throw new Exception('Unknown action: ' . $action),
    };

    echo json_encode(['success' => true] + (is_array($result) ? $result : []));

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
