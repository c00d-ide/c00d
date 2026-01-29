<?php
/**
 * c00d IDE - AI Integration
 * Supports: c00d.com API (Pro), OpenAI, Anthropic, or local LLMs
 */

namespace C00d;

class AI {
    private Database $db;
    private array $config;

    // Free tier limits
    const FREE_DAILY_REQUESTS = 20;
    const FREE_DAILY_TOKENS = 50000;

    public function __construct(Database $db, array $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'provider' => 'c00d',  // c00d, openai, anthropic, ollama, claude-cli
            'api_key' => '',
            'license_key' => '',   // c00d Pro license
            'model' => 'claude-3-5-sonnet-20241022',
            'ollama_url' => 'http://localhost:11434',
            'claude_cli_path' => 'claude',  // Path to claude CLI binary
        ], $config);
    }

    public function isProLicense(): bool {
        $licenseKey = $this->config['license_key'];
        if (empty($licenseKey)) {
            return false;
        }

        // Validate license with c00d.com
        $cached = $this->db->getSetting('license_valid_until');
        if ($cached && strtotime($cached) > time()) {
            return true;
        }

        // Check with server (cache for 24 hours)
        $result = $this->validateLicense($licenseKey);
        if ($result['valid']) {
            $this->db->setSetting('license_valid_until', date('Y-m-d H:i:s', strtotime('+24 hours')));
            return true;
        }

        return false;
    }

    private function validateLicense(string $key): array {
        $ch = curl_init('https://c00d.com/api/license/validate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['license_key' => $key]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true) ?? ['valid' => false];
        }
        return ['valid' => false];
    }

    public function checkRateLimit(): array {
        if ($this->isProLicense()) {
            return ['allowed' => true, 'remaining' => 'unlimited'];
        }

        // Check if using own API key or Claude CLI
        if (!empty($this->config['api_key']) && $this->config['provider'] !== 'c00d') {
            return ['allowed' => true, 'remaining' => 'unlimited (own key)'];
        }

        // Claude CLI uses user's own subscription
        if ($this->config['provider'] === 'claude-cli') {
            return ['allowed' => true, 'remaining' => 'unlimited (Claude subscription)'];
        }

        $usage = $this->db->getTodayAiUsage();
        $remaining = self::FREE_DAILY_REQUESTS - $usage['request_count'];

        return [
            'allowed' => $remaining > 0,
            'remaining' => max(0, $remaining),
            'limit' => self::FREE_DAILY_REQUESTS,
            'used' => $usage['request_count'],
        ];
    }

    public function chat(string $message, string $contextCode = '', string $contextFile = ''): array {
        // Check rate limit
        $rateLimit = $this->checkRateLimit();
        if (!$rateLimit['allowed']) {
            return [
                'success' => false,
                'error' => 'Daily free limit reached. Upgrade to Pro for unlimited AI access.',
                'rate_limit' => $rateLimit,
            ];
        }

        // Build messages
        $messages = [];

        // System prompt
        $systemPrompt = "You are an expert programming assistant integrated into c00d IDE.
Help users with coding tasks: explain code, fix bugs, write tests, suggest improvements.
Be concise and provide code examples when helpful.
If given code context, reference specific line numbers when relevant.";

        if ($contextCode) {
            $systemPrompt .= "\n\nUser is currently viewing this code";
            if ($contextFile) {
                $systemPrompt .= " from file: " . $contextFile;
            }
            $systemPrompt .= ":\n```\n" . $contextCode . "\n```";
        }

        // Get recent chat history for context
        $history = $this->db->getAiHistory(10);
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        // Add current message
        $messages[] = ['role' => 'user', 'content' => $message];

        // Save user message to history
        $this->db->addAiMessage('user', $message, $contextFile);

        // Call appropriate provider
        $result = match($this->config['provider']) {
            'c00d' => $this->callC00d($systemPrompt, $messages),
            'anthropic' => $this->callAnthropic($systemPrompt, $messages),
            'openai' => $this->callOpenAI($systemPrompt, $messages),
            'ollama' => $this->callOllama($systemPrompt, $messages),
            'claude-cli' => $this->callClaudeCLI($systemPrompt, $messages),
            default => ['success' => false, 'error' => 'Unknown provider'],
        };

        if ($result['success']) {
            // Save assistant response
            $this->db->addAiMessage('assistant', $result['content'], $contextFile, $result['tokens'] ?? 0);

            // Track usage
            $this->db->incrementAiUsage($result['tokens'] ?? 0);
        }

        $result['rate_limit'] = $this->checkRateLimit();
        return $result;
    }

    private function callC00d(string $system, array $messages): array {
        $licenseKey = $this->config['license_key'];

        $ch = curl_init('https://c00d.com/api/ai/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'license_key' => $licenseKey,
                'system' => $system,
                'messages' => $messages,
                'model' => $this->config['model'],
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connection error: ' . $error];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'error' => 'Invalid response from server'];
        }

        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']];
        }

        return [
            'success' => true,
            'content' => $data['content'] ?? '',
            'tokens' => $data['tokens'] ?? 0,
        ];
    }

    private function callAnthropic(string $system, array $messages): array {
        $apiKey = $this->config['api_key'];
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Anthropic API key not configured'];
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->config['model'] ?: 'claude-3-5-sonnet-20241022',
                'max_tokens' => 4096,
                'system' => $system,
                'messages' => $messages,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connection error: ' . $error];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'API error'];
        }

        $tokens = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        return [
            'success' => true,
            'content' => $data['content'][0]['text'] ?? '',
            'tokens' => $tokens,
        ];
    }

    private function callOpenAI(string $system, array $messages): array {
        $apiKey = $this->config['api_key'];
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        // Add system message
        array_unshift($messages, ['role' => 'system', 'content' => $system]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->config['model'] ?: 'gpt-4o',
                'messages' => $messages,
                'max_tokens' => 4096,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connection error: ' . $error];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return ['success' => false, 'error' => $data['error']['message'] ?? 'API error'];
        }

        return [
            'success' => true,
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens' => $data['usage']['total_tokens'] ?? 0,
        ];
    }

    private function callOllama(string $system, array $messages): array {
        $url = rtrim($this->config['ollama_url'], '/') . '/api/chat';

        // Add system message
        array_unshift($messages, ['role' => 'system', 'content' => $system]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->config['model'] ?: 'llama2',
                'messages' => $messages,
                'stream' => false,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connection error: ' . $error];
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['message'])) {
            return ['success' => false, 'error' => 'Invalid response from Ollama'];
        }

        return [
            'success' => true,
            'content' => $data['message']['content'] ?? '',
            'tokens' => 0, // Ollama doesn't report tokens
        ];
    }

    private function callClaudeCLI(string $system, array $messages): array {
        $claudePath = $this->config['claude_cli_path'] ?: 'claude';

        // Check if claude CLI is available
        $checkCmd = "which " . escapeshellarg($claudePath) . " 2>/dev/null";
        $which = trim(shell_exec($checkCmd) ?? '');

        if (empty($which)) {
            return [
                'success' => false,
                'error' => 'Claude CLI not found. Install it with: npm install -g @anthropic-ai/claude-code',
            ];
        }

        // Build the prompt with system context and conversation
        $fullPrompt = $system . "\n\n";
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $fullPrompt .= "{$role}: {$msg['content']}\n\n";
        }

        // Create a temporary file for the prompt (handles special characters better)
        $tempFile = tempnam(sys_get_temp_dir(), 'c00d_prompt_');
        file_put_contents($tempFile, $fullPrompt);

        // Run claude CLI with the prompt
        // Using --print to get just the response without interactive mode
        $cmd = escapeshellarg($claudePath) . " --print < " . escapeshellarg($tempFile) . " 2>&1";

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        // Clean up temp file
        unlink($tempFile);

        $response = implode("\n", $output);

        if ($returnCode !== 0) {
            // Check for common errors
            if (strpos($response, 'not authenticated') !== false || strpos($response, 'login') !== false) {
                return [
                    'success' => false,
                    'error' => 'Claude CLI not authenticated. Run "claude login" in terminal first.',
                ];
            }
            return [
                'success' => false,
                'error' => 'Claude CLI error: ' . $response,
            ];
        }

        return [
            'success' => true,
            'content' => trim($response),
            'tokens' => 0, // CLI doesn't report token usage
        ];
    }

    // Quick actions
    public function explainCode(string $code, string $file = ''): array {
        return $this->chat("Explain what this code does, step by step:", $code, $file);
    }

    public function fixCode(string $code, string $error, string $file = ''): array {
        return $this->chat("Fix this error: {$error}\n\nProvide the corrected code.", $code, $file);
    }

    public function generateTests(string $code, string $file = ''): array {
        return $this->chat("Generate unit tests for this code. Use appropriate testing framework based on the language.", $code, $file);
    }

    public function improveCode(string $code, string $file = ''): array {
        return $this->chat("Suggest improvements for this code (performance, readability, best practices).", $code, $file);
    }

    public function generateCommitMessage(string $diff): array {
        return $this->chat("Generate a concise git commit message for these changes. Use conventional commit format.", $diff);
    }
}
