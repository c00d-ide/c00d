<?php
/**
 * c00d IDE - Core IDE Class
 * Handles file operations, terminal, and editor state
 */

namespace C00d;

class IDE {
    private Database $db;
    private string $basePath;
    private array $config;

    // File size limits
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    const MAX_EDITABLE_SIZE = 5 * 1024 * 1024; // 5MB for editor

    public function __construct(Database $db, array $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'base_path' => getcwd(),
            'allowed_paths' => [], // Empty = allow all under base_path
            'denied_paths' => ['.git', 'node_modules', 'vendor', '.env'],
            'hidden_files' => true, // Show hidden files
        ], $config);
        $this->basePath = realpath($this->config['base_path']) ?: getcwd();
    }

    // Security: validate path is within allowed directories
    private function validatePath(string $path): string {
        // Resolve to absolute path
        if (substr($path, 0, 1) !== '/') {
            $path = $this->basePath . '/' . $path;
        }

        $realPath = realpath($path);

        // For new files, check parent directory
        if (!$realPath) {
            $parentPath = realpath(dirname($path));
            if (!$parentPath) {
                throw new \Exception('Invalid path');
            }
            $realPath = $parentPath . '/' . basename($path);
        }

        // Must be under base path
        if (strpos($realPath, $this->basePath) !== 0) {
            throw new \Exception('Access denied: path outside allowed directory');
        }

        // Check denied paths
        foreach ($this->config['denied_paths'] as $denied) {
            if (strpos($realPath, '/' . $denied) !== false) {
                throw new \Exception('Access denied: restricted path');
            }
        }

        return $realPath;
    }

    // File operations
    public function listDirectory(string $path = '.'): array {
        $fullPath = $this->validatePath($path);

        if (!is_dir($fullPath)) {
            throw new \Exception('Not a directory');
        }

        $items = [];
        $files = scandir($fullPath);

        // Check if we're at the base path (root)
        $isAtRoot = ($fullPath === $this->basePath);

        foreach ($files as $file) {
            if ($file === '.') continue;

            // Hide ".." when at the root (can't go higher)
            if ($file === '..' && $isAtRoot) continue;

            // Skip hidden files if configured
            if (!$this->config['hidden_files'] && $file !== '..' && substr($file, 0, 1) === '.') {
                continue;
            }

            $itemPath = $fullPath . '/' . $file;
            $isDir = is_dir($itemPath);

            $items[] = [
                'name' => $file,
                'path' => $itemPath,
                'relative_path' => str_replace($this->basePath, '', $itemPath),
                'is_dir' => $isDir,
                'size' => $isDir ? 0 : filesize($itemPath),
                'modified' => filemtime($itemPath),
                'readable' => is_readable($itemPath),
                'writable' => is_writable($itemPath),
                'extension' => $isDir ? '' : pathinfo($file, PATHINFO_EXTENSION),
            ];
        }

        // Sort: .. first, then directories, then files
        usort($items, function($a, $b) {
            if ($a['name'] === '..') return -1;
            if ($b['name'] === '..') return 1;
            if ($a['is_dir'] !== $b['is_dir']) {
                return $b['is_dir'] - $a['is_dir'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => $fullPath,
            'relative_path' => str_replace($this->basePath, '', $fullPath) ?: '/',
            'items' => $items,
        ];
    }

    public function readFile(string $path): array {
        $fullPath = $this->validatePath($path);

        if (!file_exists($fullPath)) {
            throw new \Exception('File not found');
        }

        if (is_dir($fullPath)) {
            throw new \Exception('Path is a directory');
        }

        $size = filesize($fullPath);
        if ($size > self::MAX_FILE_SIZE) {
            throw new \Exception('File too large (max ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB)');
        }

        $content = file_get_contents($fullPath);
        $isBinary = $this->isBinaryContent($content);

        // Track recent file
        $this->db->addRecentFile($fullPath);

        return [
            'path' => $fullPath,
            'relative_path' => str_replace($this->basePath, '', $fullPath),
            'content' => $isBinary ? null : $content,
            'content_base64' => $isBinary ? base64_encode($content) : null,
            'is_binary' => $isBinary,
            'size' => $size,
            'editable' => !$isBinary && $size <= self::MAX_EDITABLE_SIZE,
            'language' => $this->detectLanguage($fullPath),
            'modified' => filemtime($fullPath),
        ];
    }

    public function writeFile(string $path, string $content, bool $isBase64 = false): array {
        $fullPath = $this->validatePath($path);

        if ($isBase64) {
            $content = base64_decode($content);
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            throw new \Exception('Parent directory does not exist');
        }

        $bytes = file_put_contents($fullPath, $content);
        if ($bytes === false) {
            throw new \Exception('Failed to write file');
        }

        return [
            'path' => $fullPath,
            'relative_path' => str_replace($this->basePath, '', $fullPath),
            'size' => $bytes,
            'modified' => filemtime($fullPath),
        ];
    }

    public function createDirectory(string $path): array {
        $fullPath = $this->validatePath($path);

        if (file_exists($fullPath)) {
            throw new \Exception('Path already exists');
        }

        if (!mkdir($fullPath, 0755, true)) {
            throw new \Exception('Failed to create directory');
        }

        return [
            'path' => $fullPath,
            'relative_path' => str_replace($this->basePath, '', $fullPath),
        ];
    }

    public function delete(string $path): array {
        $fullPath = $this->validatePath($path);

        if (!file_exists($fullPath)) {
            throw new \Exception('Path not found');
        }

        // Safety check
        if ($fullPath === $this->basePath) {
            throw new \Exception('Cannot delete base directory');
        }

        if (is_dir($fullPath)) {
            $this->deleteDirectory($fullPath);
        } else {
            unlink($fullPath);
        }

        return ['deleted' => $fullPath];
    }

    private function deleteDirectory(string $dir): void {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function rename(string $oldPath, string $newPath): array {
        $fullOldPath = $this->validatePath($oldPath);
        $fullNewPath = $this->validatePath($newPath);

        if (!file_exists($fullOldPath)) {
            throw new \Exception('Source path not found');
        }

        if (file_exists($fullNewPath)) {
            throw new \Exception('Destination already exists');
        }

        if (!rename($fullOldPath, $fullNewPath)) {
            throw new \Exception('Failed to rename');
        }

        return [
            'old_path' => $fullOldPath,
            'new_path' => $fullNewPath,
        ];
    }

    // Terminal
    public function executeCommand(string $command, string $cwd = null): array {
        $workingDir = $cwd ? $this->validatePath($cwd) : $this->basePath;

        if (!is_dir($workingDir)) {
            $workingDir = $this->basePath;
        }

        // Save to history
        $this->db->addCommand($command, $workingDir);

        // Execute
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Set up environment with proper PATH
        $env = [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => getenv('HOME') ?: '/tmp',
            'USER' => getenv('USER') ?: 'www-data',
            'TERM' => 'xterm-256color',
        ];

        $process = proc_open($command, $descriptors, $pipes, $workingDir, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'error' => 'Failed to execute command',
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'success' => $exitCode === 0,
            'output' => $stdout,
            'error_output' => $stderr,
            'exit_code' => $exitCode,
            'cwd' => $workingDir,
        ];
    }

    public function getCommandHistory(int $limit = 100): array {
        return $this->db->getCommandHistory($limit);
    }

    // Search
    public function searchFiles(string $query, string $path = '.', array $options = []): array {
        $fullPath = $this->validatePath($path);
        $options = array_merge([
            'max_results' => 100,
            'include_content' => true,
            'file_pattern' => '*',
            'case_sensitive' => false,
        ], $options);

        $results = [];
        $this->searchRecursive($fullPath, $query, $options, $results);

        return [
            'query' => $query,
            'path' => $fullPath,
            'results' => array_slice($results, 0, $options['max_results']),
            'total' => count($results),
        ];
    }

    private function searchRecursive(string $dir, string $query, array $options, array &$results): void {
        if (count($results) >= $options['max_results']) {
            return;
        }

        $files = @scandir($dir);
        if (!$files) return;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $fullPath = $dir . '/' . $file;

            // Skip denied paths
            foreach ($this->config['denied_paths'] as $denied) {
                if (strpos($fullPath, '/' . $denied) !== false) continue 2;
            }

            if (is_dir($fullPath)) {
                $this->searchRecursive($fullPath, $query, $options, $results);
            } else {
                // Check if filename matches
                if (fnmatch($options['file_pattern'], $file)) {
                    if ($options['include_content'] && filesize($fullPath) < 1024 * 1024) {
                        $content = @file_get_contents($fullPath);
                        if ($content !== false) {
                            $flags = $options['case_sensitive'] ? 0 : PREG_OFFSET_CAPTURE;
                            $pattern = '/' . preg_quote($query, '/') . '/';
                            if (!$options['case_sensitive']) $pattern .= 'i';

                            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                                foreach ($matches[0] as $match) {
                                    $lineNum = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                                    $results[] = [
                                        'file' => $fullPath,
                                        'relative_path' => str_replace($this->basePath, '', $fullPath),
                                        'line' => $lineNum,
                                        'match' => $match[0],
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Helpers
    private function isBinaryContent(string $content): bool {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', substr($content, 0, 8192)) === 1;
    }

    public function detectLanguage(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $map = [
            'js' => 'javascript', 'mjs' => 'javascript', 'jsx' => 'javascript',
            'ts' => 'typescript', 'tsx' => 'typescript',
            'py' => 'python', 'pyw' => 'python',
            'php' => 'php', 'phtml' => 'php',
            'html' => 'html', 'htm' => 'html',
            'css' => 'css', 'scss' => 'scss', 'less' => 'less', 'sass' => 'sass',
            'json' => 'json', 'jsonc' => 'json',
            'xml' => 'xml', 'svg' => 'xml',
            'yaml' => 'yaml', 'yml' => 'yaml',
            'md' => 'markdown', 'markdown' => 'markdown',
            'sql' => 'sql',
            'sh' => 'shell', 'bash' => 'shell', 'zsh' => 'shell',
            'dockerfile' => 'dockerfile',
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'c' => 'c', 'h' => 'c',
            'cpp' => 'cpp', 'cc' => 'cpp', 'cxx' => 'cpp', 'hpp' => 'cpp',
            'rb' => 'ruby',
            'swift' => 'swift',
            'kt' => 'kotlin', 'kts' => 'kotlin',
            'lua' => 'lua',
            'r' => 'r',
            'pl' => 'perl', 'pm' => 'perl',
            'ini' => 'ini', 'conf' => 'ini', 'cfg' => 'ini',
            'toml' => 'ini',
            'env' => 'ini',
            'vue' => 'vue',
            'svelte' => 'svelte',
        ];

        // Special cases for files without extension
        $basename = strtolower(basename($filename));
        if ($basename === 'dockerfile') return 'dockerfile';
        if ($basename === 'makefile') return 'makefile';
        if ($basename === '.gitignore') return 'ini';
        if ($basename === '.env') return 'ini';

        return $map[$ext] ?? 'plaintext';
    }

    // Editor state
    public function getOpenTabs(): array {
        return $this->db->getOpenTabs();
    }

    public function saveTabState(string $path, int $line, int $col, bool $isActive): void {
        $this->db->saveTab($path, $line, $col, $isActive);
    }

    public function closeTabState(string $path): void {
        $this->db->closeTab($path);
    }

    // Settings
    public function getSetting(string $key, $default = null) {
        return $this->db->getSetting($key, $default);
    }

    public function setSetting(string $key, $value): void {
        $this->db->setSetting($key, $value);
    }

    public function getRecentFiles(int $limit = 20): array {
        return $this->db->getRecentFiles($limit);
    }

    public function getBasePath(): string {
        return $this->basePath;
    }
}
