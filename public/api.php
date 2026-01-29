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

// Session handling
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

        // Info
        'info' => [
            'base_path' => $ide->getBasePath(),
            'php_version' => PHP_VERSION,
            'ai_provider' => $config['ai']['provider'] ?? 'c00d',
            'is_pro' => $ai->isProLicense(),
        ],

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
