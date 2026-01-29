/**
 * c00d IDE - WebSocket Terminal Server
 * Provides a real PTY terminal via WebSocket
 */

const WebSocket = require('ws');
const pty = require('node-pty');
const os = require('os');

const PORT = process.env.TERMINAL_PORT || 3456;
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || '').split(',').filter(Boolean);

// Create WebSocket server
const wss = new WebSocket.Server({
    port: PORT,
    verifyClient: (info, callback) => {
        // Allow all origins if none specified, otherwise check
        if (ALLOWED_ORIGINS.length === 0) {
            callback(true);
            return;
        }
        const origin = info.origin || info.req.headers.origin;
        const allowed = ALLOWED_ORIGINS.some(o => origin && origin.includes(o));
        callback(allowed);
    }
});

console.log(`Terminal WebSocket server running on port ${PORT}`);

// Track active sessions
const sessions = new Map();

wss.on('connection', (ws, req) => {
    console.log('New terminal connection');

    // Get working directory from query string
    const url = new URL(req.url, `http://localhost:${PORT}`);
    const cwd = url.searchParams.get('cwd') || process.env.HOME || '/tmp';

    // Spawn PTY
    const shell = os.platform() === 'win32' ? 'powershell.exe' : 'bash';
    const ptyProcess = pty.spawn(shell, [], {
        name: 'xterm-256color',
        cols: 80,
        rows: 24,
        cwd: cwd,
        env: {
            ...process.env,
            TERM: 'xterm-256color',
            COLORTERM: 'truecolor',
        }
    });

    console.log(`Spawned PTY with PID ${ptyProcess.pid} in ${cwd}`);

    // Store session
    sessions.set(ws, ptyProcess);

    // Forward PTY output to WebSocket
    ptyProcess.onData((data) => {
        try {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'output', data }));
            }
        } catch (e) {
            console.error('Error sending data:', e);
        }
    });

    ptyProcess.onExit(({ exitCode, signal }) => {
        console.log(`PTY exited with code ${exitCode}, signal ${signal}`);
        try {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'exit', exitCode, signal }));
                ws.close();
            }
        } catch (e) {
            // Ignore
        }
    });

    // Handle WebSocket messages
    ws.on('message', (message) => {
        try {
            const msg = JSON.parse(message.toString());

            switch (msg.type) {
                case 'input':
                    // Send input to PTY
                    ptyProcess.write(msg.data);
                    break;

                case 'resize':
                    // Resize PTY
                    if (msg.cols && msg.rows) {
                        ptyProcess.resize(msg.cols, msg.rows);
                    }
                    break;

                case 'ping':
                    ws.send(JSON.stringify({ type: 'pong' }));
                    break;
            }
        } catch (e) {
            console.error('Error handling message:', e);
        }
    });

    // Handle WebSocket close
    ws.on('close', () => {
        console.log('Terminal connection closed');
        const proc = sessions.get(ws);
        if (proc) {
            proc.kill();
            sessions.delete(ws);
        }
    });

    // Handle WebSocket error
    ws.on('error', (err) => {
        console.error('WebSocket error:', err);
    });
});

// Cleanup on shutdown
process.on('SIGINT', () => {
    console.log('Shutting down...');
    sessions.forEach((proc) => proc.kill());
    wss.close();
    process.exit(0);
});

process.on('SIGTERM', () => {
    console.log('Shutting down...');
    sessions.forEach((proc) => proc.kill());
    wss.close();
    process.exit(0);
});
