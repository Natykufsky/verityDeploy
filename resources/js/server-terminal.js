import { Terminal } from 'xterm';
import { FitAddon } from '@xterm/addon-fit';
import 'xterm/css/xterm.css';

window.verityServerTerminal = function verityServerTerminal(options) {
    return {
        componentId: options.componentId,
        feedUrl: options.feedUrl,
        terminalElement: null,
        terminal: null,
        fitAddon: null,
        commandBuffer: '',
        commandHistory: [],
        commandHistoryIndex: -1,
        promptText: options.promptText || '$ ',
        sessionId: options.sessionId || null,
        bridgeUrl: options.bridgeUrl || null,
        bridgeSocket: null,
        bridgeConnected: false,
        bridgeState: options.bridgeUrl ? 'connecting' : 'disabled',
        bridgeReconnectTimer: null,
        bridgeReconnectCountdownTimer: null,
        bridgeReconnectSeconds: 0,
        bridgeToast: '',
        bridgeToastTimer: null,
        suggestions: [],
        autocompleteMatches: [],
        autocompleteQuery: '',
        suggestionIndex: -1,
        autocompleteHint: '',
        welcomeTitle: options.welcomeTitle || 'verityDeploy terminal',
        welcomeText: options.welcomeText || 'Connected to the server console. Use Tab for autocomplete and the left sidebar for presets.',
        renderedRuns: new Set(),
        runOutputLengths: {},
        pollTimer: null,
        initialized: false,
        focusHandler: null,
        resetHandler: null,

        init() {
            this.terminalElement = this.$refs.terminal;
            this.fitAddon = new FitAddon();
            this.terminal = new Terminal({
                cursorBlink: true,
                fontFamily: '"Fira Code", "Cascadia Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
                fontSize: 13,
                lineHeight: 1.35,
                convertEol: true,
                theme: {
                    background: '#020617',
                    foreground: '#e2e8f0',
                    cursor: '#fbbf24',
                    selectionBackground: 'rgba(245, 158, 11, 0.24)',
                    black: '#020617',
                    red: '#fb7185',
                    green: '#4ade80',
                    yellow: '#facc15',
                    blue: '#60a5fa',
                    magenta: '#c084fc',
                    cyan: '#22d3ee',
                    white: '#f8fafc',
                },
            });

            this.terminal.loadAddon(this.fitAddon);
            this.terminal.open(this.terminalElement);
            this.fitAddon.fit();
            this.focusTerminal();

            this.terminal.writeln(`\x1b[38;5;214m${this.welcomeTitle}\x1b[0m`);
            this.terminal.writeln(`\x1b[38;5;245m${this.welcomeText}\x1b[0m`);
            if (this.sessionId) {
                this.terminal.writeln(`\x1b[38;5;111msession #${this.sessionId} ready\x1b[0m`);
            }
            this.writePrompt();

            this.terminal.onData((data) => this.handleInput(data));
            this.initialized = true;

            this.refreshFeed();
            this.pollTimer = setInterval(() => this.refreshFeed(), 1800);
            this.focusHandler = () => this.focusTerminal();
            window.addEventListener('verity-focus-server-terminal', this.focusHandler);
            this.resetHandler = () => this.resetTerminalBuffer();
            window.addEventListener('verity-reset-server-terminal-workspace', this.resetHandler);

            window.addEventListener('resize', () => {
                this.fitAddon.fit();
            });
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }

            if (this.focusHandler) {
                window.removeEventListener('verity-focus-server-terminal', this.focusHandler);
            }

            if (this.resetHandler) {
                window.removeEventListener('verity-reset-server-terminal-workspace', this.resetHandler);
            }

            this.disconnectBridge();
        },

        focusTerminal() {
            if (!this.terminal) {
                return;
            }

            window.requestAnimationFrame(() => {
                this.terminal.focus();
            });
        },

        setBridgeState(state) {
            this.bridgeState = state;
        },

        setBridgeToast(message, timeout = 2200) {
            this.bridgeToast = message;

            if (this.bridgeToastTimer) {
                clearTimeout(this.bridgeToastTimer);
                this.bridgeToastTimer = null;
            }

            if (message && timeout > 0) {
                this.bridgeToastTimer = window.setTimeout(() => {
                    this.bridgeToast = '';
                    this.bridgeToastTimer = null;
                }, timeout);
            }
        },

        clearBridgeReconnectCountdown() {
            if (this.bridgeReconnectCountdownTimer) {
                clearInterval(this.bridgeReconnectCountdownTimer);
                this.bridgeReconnectCountdownTimer = null;
            }

            this.bridgeReconnectSeconds = 0;
        },

        bridgeStatusLabel() {
            return {
                connected: 'bridge live',
                connecting: 'bridge connecting',
                reconnecting: 'bridge reconnecting',
                offline: 'bridge offline',
                disabled: 'bridge disabled',
            }[this.bridgeState] || 'bridge offline';
        },

        bridgeStatusClasses() {
            return {
                connected: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
                connecting: 'border-cyan-400/20 bg-cyan-500/10 text-cyan-100',
                reconnecting: 'border-amber-400/20 bg-amber-500/10 text-amber-100',
                offline: 'border-rose-400/20 bg-rose-500/10 text-rose-100',
                disabled: 'border-slate-500/20 bg-slate-500/10 text-slate-300',
            }[this.bridgeState] || 'border-rose-400/20 bg-rose-500/10 text-rose-100';
        },

        bridgeDotClasses() {
            return {
                connected: 'bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.85)]',
                connecting: 'bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.85)]',
                reconnecting: 'bg-amber-300 shadow-[0_0_12px_rgba(251,191,36,0.85)]',
                offline: 'bg-rose-300 shadow-[0_0_12px_rgba(251,113,133,0.85)]',
                disabled: 'bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.45)]',
            }[this.bridgeState] || 'bg-rose-300';
        },

        resetTerminalBuffer() {
            this.commandBuffer = '';
            this.commandHistoryIndex = this.commandHistory.length;
            this.suggestionIndex = -1;
            this.autocompleteMatches = [];
            this.autocompleteQuery = '';
            this.autocompleteHint = '';

            if (this.terminal) {
                this.terminal.writeln('\r\n\x1b[38;5;245mTerminal workspace reset.\x1b[0m');
                this.writePrompt();
                this.focusTerminal();
            }
        },

        async refreshFeed() {
            if (this.bridgeConnected) {
                return;
            }

            try {
                const response = await fetch(`${this.feedUrl}?t=${Date.now()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                this.syncFeed(payload);
            } catch (error) {
                // Keep polling; transient failures should not break the terminal.
            }
        },

        syncFeed(payload) {
            if (this.bridgeConnected) {
                return;
            }

            if (payload.prompt) {
                this.promptText = payload.prompt;
            }

            if (Array.isArray(payload.suggestions)) {
                this.suggestions = payload.suggestions;
            }

            const runs = Array.isArray(payload.runs) ? payload.runs : [];
            const orderedRuns = [...runs].reverse();

            if (!this.renderedRuns.size) {
                this.terminal.clear();
                this.terminal.writeln(`\x1b[38;5;214m${this.welcomeTitle}\x1b[0m`);
                this.terminal.writeln(`\x1b[38;5;245m${this.welcomeText}\x1b[0m`);
                if (this.sessionId) {
                    this.terminal.writeln(`\x1b[38;5;111msession #${this.sessionId} ready\x1b[0m`);
                }

                orderedRuns.forEach((run) => {
                    this.renderRun(run, true);
                });

                this.writePrompt();
                this.focusTerminal();
                return;
            }

            orderedRuns.forEach((run) => {
                if (!this.renderedRuns.has(String(run.id))) {
                    this.renderRun(run, true);
                    this.writePrompt();
                    return;
                }

                const previousLength = this.runOutputLengths[String(run.id)] || 0;
                const currentLength = (run.output || '').length;

                if (currentLength > previousLength) {
                    const chunk = (run.output || '').slice(previousLength);
                    this.writeColoredChunk(chunk);
                    this.runOutputLengths[String(run.id)] = currentLength;
                    this.terminal.scrollToBottom();
                }
            });
        },

        renderRun(run, forceOutput = false) {
            const runKey = String(run.id);
            const startedAt = run.started_at ? new Date(run.started_at).toLocaleTimeString([], { hour12: false }) : '--:--:--';
            const status = (run.status || 'unknown').toUpperCase();

            this.terminal.writeln('');
            this.terminal.writeln(this.colorizeLine(`[${startedAt}] $ ${run.command} [${status}]`));

            if (run.error_message) {
                this.terminal.writeln(this.colorizeLine(`ERROR: ${run.error_message}`));
            }

            const output = run.output || '';
            if (output) {
                this.writeColoredChunk(output);
            } else if (forceOutput) {
                this.terminal.writeln('\x1b[38;5;245mNo terminal output yet.\x1b[0m');
            }

            this.renderedRuns.add(runKey);
            this.runOutputLengths[runKey] = output.length;
        },

        writeColoredChunk(chunk) {
            const text = String(chunk || '').replace(/\r\n/g, '\n');
            const lines = text.split('\n');
            const endsWithNewline = text.endsWith('\n');

            lines.forEach((line, index) => {
                if (index === lines.length - 1 && line === '') {
                    return;
                }

                const colored = this.colorizeLine(line);

                if (index === lines.length - 1 && !endsWithNewline) {
                    this.terminal.write(colored);
                    return;
                }

                this.terminal.writeln(colored);
            });
        },

        colorizeLine(line) {
            const text = String(line ?? '').trimEnd();

            if (text === '') {
                return '';
            }

            if (/error|failed|fatal|exception|denied|unable|invalid|permission denied|timed out/i.test(text)) {
                return `\x1b[1;38;5;210m${text}\x1b[0m`;
            }

            if (/warning|caution|attention|deprecated|skipped|optional/i.test(text)) {
                return `\x1b[1;38;5;221m${text}\x1b[0m`;
            }

            if (/success|completed|done|ok|activated|updated|installed|restored|linked|synced|deployed|finished/i.test(text)) {
                return `\x1b[1;38;5;114m${text}\x1b[0m`;
            }

            if (/^(installing|cloning|fetching|downloading|building|compiling|migrating|bootstrapping|cleaning|rotating|refreshing|backing up)/i.test(text)) {
                return `\x1b[1;38;5;215m${text}\x1b[0m`;
            }

            if (/^\s*\d+%/.test(text) || /\[\s*\d+\/\d+\s*\]/.test(text) || /progress/i.test(text)) {
                return `\x1b[1;38;5;117m${text}\x1b[0m`;
            }

            if (/^\s*(\+|>|->|=>|\*|•|·|◦|✓|✗)/.test(text)) {
                return `\x1b[1;38;5;81m${text}\x1b[0m`;
            }

            if (/^\$ /.test(text) || /^\[[0-9]{2}:[0-9]{2}:[0-9]{2}\]/.test(text)) {
                return `\x1b[1;38;5;111m${text}\x1b[0m`;
            }

            if (/^\{.*\}$/.test(text) || /^\[.*\]$/.test(text)) {
                return `\x1b[38;5;183m${text}\x1b[0m`;
            }

            if (/^(line \d+|step \d+|stage \d+)/i.test(text)) {
                return `\x1b[38;5;223m${text}\x1b[0m`;
            }

            if (/^==>/.test(text) || /^--/.test(text) || /^::/.test(text)) {
                return `\x1b[38;5;123m${text}\x1b[0m`;
            }

            return `\x1b[38;5;252m${text}\x1b[0m`;
        },

        writePrompt() {
            if (this.bridgeConnected) {
                return;
            }

            this.terminal.write(`\x1b[38;5;82m${this.promptText}\x1b[0m`);
            this.commandBuffer = '';
            this.commandHistoryIndex = this.commandHistory.length;
            this.suggestionIndex = -1;
            this.autocompleteMatches = [];
            this.autocompleteQuery = '';
            this.autocompleteHint = '';
        },

        handleInput(data) {
            if (!data) {
                return;
            }

            if (this.bridgeConnected && this.bridgeSocket?.readyState === WebSocket.OPEN) {
                this.bridgeSocket.send(JSON.stringify({
                    type: 'input',
                    data,
                }));

                return;
            }

            for (const char of data) {
                if (char === '\r') {
                    this.submitCommand();
                    continue;
                }

                if (char === '\u0003') {
                    this.terminal.write('^C\r\n');
                    this.writePrompt();
                    continue;
                }

                if (char === '\u000c') {
                    this.terminal.clear();
                    this.writePrompt();
                    continue;
                }

                if (char === '\u007f') {
                    this.handleBackspace();
                    continue;
                }

                if (char === '\t') {
                    this.autocomplete();
                    continue;
                }

                if (char === '\u001b') {
                    continue;
                }

                if (char === '\u001b[A') {
                    this.historyPrevious();
                    continue;
                }

                if (char === '\u001b[B') {
                    this.historyNext();
                    continue;
                }

                if (char === '\u001b[C' || char === '\u001b[D') {
                    continue;
                }

                this.commandBuffer += char;
                this.terminal.write(char);
                this.suggestionIndex = -1;
                this.autocompleteQuery = '';
                this.autocompleteHint = '';
            }
        },

        handleBackspace() {
            if (!this.commandBuffer.length) {
                return;
            }

            this.commandBuffer = this.commandBuffer.slice(0, -1);
            this.terminal.write('\b \b');
            this.suggestionIndex = -1;
            this.autocompleteQuery = '';
            this.autocompleteHint = '';
        },

        historyPrevious() {
            if (!this.commandHistory.length) {
                return;
            }

            if (this.commandHistoryIndex <= 0) {
                this.commandHistoryIndex = 0;
            } else {
                this.commandHistoryIndex -= 1;
            }

            this.replaceBuffer(this.commandHistory[this.commandHistoryIndex] ?? '');
        },

        historyNext() {
            if (!this.commandHistory.length) {
                return;
            }

            if (this.commandHistoryIndex >= this.commandHistory.length - 1) {
                this.commandHistoryIndex = this.commandHistory.length;
                this.replaceBuffer('');
                return;
            }

            this.commandHistoryIndex += 1;
            this.replaceBuffer(this.commandHistory[this.commandHistoryIndex] ?? '');
        },

        autocomplete() {
            const prefix = this.commandBuffer.trim();
            const matches = this.suggestions.filter((item) => {
                const command = String(item.command || '');
                const label = String(item.label || '');
                const description = String(item.description || '');
                const tags = Array.isArray(item.tags) ? item.tags.join(' ') : '';
                const group = String(item.group || '');
                const haystack = `${command} ${label} ${description} ${tags} ${group}`.toLowerCase();

                if (prefix === '') {
                    return true;
                }

                return command.toLowerCase().startsWith(prefix.toLowerCase())
                    || haystack.includes(prefix.toLowerCase());
            });

            if (!matches.length) {
                this.terminal.write('\x07');
                return;
            }

            if (this.autocompleteQuery !== prefix) {
                this.autocompleteQuery = prefix;
                this.autocompleteMatches = matches;
                this.suggestionIndex = 0;
            } else {
                this.autocompleteMatches = matches;
                this.suggestionIndex = (this.suggestionIndex + 1) % this.autocompleteMatches.length;
            }

            const suggestion = this.autocompleteMatches[this.suggestionIndex] || this.autocompleteMatches[0];
            this.replaceBuffer(String(suggestion.command || ''));
            this.autocompleteHint = this.formatAutocompleteHint(suggestion, this.autocompleteMatches.length);
        },

        replaceBuffer(value) {
            while (this.commandBuffer.length) {
                this.terminal.write('\b \b');
                this.commandBuffer = this.commandBuffer.slice(0, -1);
            }

            this.commandBuffer = value;
            this.terminal.write(value);
        },

        formatAutocompleteHint(suggestion, count) {
            if (!suggestion) {
                return '';
            }

            const label = String(suggestion.label || suggestion.command || '');
            const source = String(suggestion.source || 'Suggestion');
            const group = String(suggestion.group || 'General');
            const tags = Array.isArray(suggestion.tags) && suggestion.tags.length ? ` • ${suggestion.tags.join(', ')}` : '';

            return `${label} · ${source} · ${group}${tags}${count > 1 ? ` · ${count} matches` : ''}`;
        },

        submitCommand() {
            const command = this.commandBuffer.trim();

            this.terminal.write('\r\n');

            if (!command) {
                this.writePrompt();
                return;
            }

            if (this.bridgeConnected && this.bridgeSocket?.readyState === WebSocket.OPEN) {
                this.bridgeSocket.send(JSON.stringify({
                    type: 'input',
                    data: `${command}\r`,
                }));
                this.commandBuffer = '';
                this.autocompleteHint = '';
                this.focusTerminal();

                return;
            }

            this.commandHistory.push(command);
            this.commandHistoryIndex = this.commandHistory.length;
            this.commandBuffer = '';
            this.autocompleteHint = '';

            const component = window.Livewire?.find?.(this.componentId);
            if (component) {
                component.call('runCommandFromTerminal', command);
            }

            this.writePrompt();
            this.focusTerminal();
        },

        connectBridge() {
            if (!this.bridgeUrl) {
                this.setBridgeState('disabled');
                return;
            }

            if (this.bridgeConnected || this.bridgeSocket) {
                return;
            }

            try {
                this.setBridgeState(this.bridgeReconnectTimer ? 'reconnecting' : 'connecting');
                this.bridgeSocket = new WebSocket(this.bridgeUrl);
            } catch (error) {
                this.bridgeSocket = null;
                this.setBridgeState('offline');
                return;
            }

            this.bridgeSocket.addEventListener('open', () => {
                this.bridgeConnected = true;
                this.bridgeReconnectTimer = null;
                this.clearBridgeReconnectCountdown();
                this.setBridgeState('connected');
                this.setBridgeToast('bridge live');
                this.commandBuffer = '';
                this.commandHistoryIndex = this.commandHistory.length;
                this.autocompleteHint = '';
                this.autocompleteMatches = [];
                this.autocompleteQuery = '';
                this.suggestionIndex = -1;

                if (this.terminal) {
                    this.terminal.clear();
                    this.terminal.writeln(`\x1b[38;5;214m${this.welcomeTitle}\x1b[0m`);
                    this.terminal.writeln(`\x1b[38;5;245m${this.welcomeText}\x1b[0m`);
                    if (this.sessionId) {
                        this.terminal.writeln(`\x1b[38;5;111msession #${this.sessionId} ready\x1b[0m`);
                    }
                }

                this.syncBridgeSize();
                this.focusTerminal();
            });

            this.bridgeSocket.addEventListener('message', (event) => {
                this.handleBridgeMessage(event.data);
            });

            this.bridgeSocket.addEventListener('close', () => {
                this.bridgeConnected = false;
                this.bridgeSocket = null;
                this.setBridgeState(this.open ? 'reconnecting' : 'offline');
                this.setBridgeToast(this.open ? 'bridge reconnecting' : 'bridge offline');
                this.scheduleBridgeReconnect();
            });

            this.bridgeSocket.addEventListener('error', () => {
                this.bridgeConnected = false;
                this.setBridgeState('offline');
                this.setBridgeToast('bridge offline');
            });
        },

        scheduleBridgeReconnect() {
            if (!this.open || !this.bridgeUrl || this.bridgeReconnectTimer) {
                return;
            }

            this.setBridgeState('reconnecting');
            this.clearBridgeReconnectCountdown();
            this.bridgeReconnectSeconds = 2;
            this.bridgeReconnectCountdownTimer = window.setInterval(() => {
                if (this.bridgeReconnectSeconds > 0) {
                    this.bridgeReconnectSeconds -= 1;
                }

                if (this.bridgeReconnectSeconds <= 0) {
                    this.clearBridgeReconnectCountdown();
                }
            }, 1000);
            this.bridgeReconnectTimer = window.setTimeout(() => {
                this.bridgeReconnectTimer = null;
                this.clearBridgeReconnectCountdown();
                this.connectBridge();
            }, 2000);
        },

        disconnectBridge() {
            if (this.bridgeReconnectTimer) {
                clearTimeout(this.bridgeReconnectTimer);
                this.bridgeReconnectTimer = null;
            }

            this.clearBridgeReconnectCountdown();

            if (this.bridgeToastTimer) {
                clearTimeout(this.bridgeToastTimer);
                this.bridgeToastTimer = null;
            }

            if (this.bridgeSocket) {
                try {
                    this.bridgeSocket.close(1000, 'terminal closed');
                } catch (error) {}
            }

            this.bridgeConnected = false;
            this.bridgeSocket = null;
            this.setBridgeState(this.bridgeUrl ? 'offline' : 'disabled');
            if (!this.bridgeUrl) {
                this.bridgeToast = '';
            }
        },

        handleBridgeMessage(payload) {
            let message = payload;

            try {
                message = JSON.parse(payload);
            } catch (error) {
                message = {
                    type: 'output',
                    data: String(payload || ''),
                };
            }

            const type = String(message?.type || 'output');

            if (type === 'ready') {
                if (message?.session?.prompt) {
                    this.promptText = message.session.prompt;
                }

                if (message?.session?.id) {
                    this.sessionId = message.session.id;
                }

                this.setBridgeToast('bridge live', 1800);

                return;
            }

            if (type === 'resized') {
                return;
            }

            if (type === 'closed') {
                this.bridgeConnected = false;
                this.bridgeSocket = null;
                this.setBridgeState(this.open ? 'reconnecting' : 'offline');
                this.writeColoredChunk(String(message?.message || 'live shell closed.'));
                this.commandBuffer = '';
                this.autocompleteHint = '';
                this.setBridgeToast(this.open ? 'bridge reconnecting' : 'bridge offline');
                this.scheduleBridgeReconnect();
                return;
            }

            if (type === 'error') {
                this.setBridgeState('offline');
                this.writeColoredChunk(String(message?.message || 'bridge error.'));
                this.commandBuffer = '';
                this.setBridgeToast('bridge offline');
                return;
            }

            const data = String(message?.data || '');
            if (data !== '') {
                this.writeColoredChunk(data);
                this.terminal.scrollToBottom();
            }
        },

        syncBridgeSize() {
            if (!this.bridgeSocket || this.bridgeSocket.readyState !== WebSocket.OPEN || !this.terminal) {
                return;
            }

            this.bridgeSocket.send(JSON.stringify({
                type: 'resize',
                cols: this.terminal.cols || 120,
                rows: this.terminal.rows || 32,
            }));
        },
    };
};

window.verityServerTerminalModal = function verityServerTerminalModal() {
    return {
        componentId: null,
        feedUrl: null,
        promptText: null,
        open: false,
        isMobile: window.matchMedia('(max-width: 1023px)').matches,
        showMobileTools: false,
        showTerminalTools: false,
        width: 1120,
        height: 760,
        left: 0,
        top: 0,
        autoCenterOnFirstOpen: true,
        hasOpenedOnce: false,
        minWidth: 840,
        minHeight: 620,
        maxWidth: 1440,
        maxHeight: 980,
        terminalReady: false,
        terminalApi: null,
        bridgeUrl: null,
        bridgeState: 'disabled',
        bridgeReconnectSeconds: 0,
        bridgeReconnectTimer: null,
        bridgeToast: '',
        bridgeToastTimer: null,
        terminalBootAttempts: 0,
        autocompleteHint: '',
        sessionId: null,
        sessionOpenUrl: null,
        sessionHeartbeatUrl: null,
        sessionCloseUrl: null,
        sessionHeartbeatTimer: null,
        historyFilter: 'all',
        presetSearch: '',
        presetGroupFilter: '',
        presetTagFilter: '',
        storageKey: 'verity-server-terminal',

        initModal(config = {}, historyFilter = null, presetSearch = null, presetGroupFilter = null, presetTagFilter = null) {
            Object.assign(this, config || {});
            this.open = true;

            if (historyFilter !== null && historyFilter !== undefined) {
                this.historyFilter = historyFilter;
            }
            if (presetSearch !== null && presetSearch !== undefined) {
                this.presetSearch = presetSearch;
            }
            if (presetGroupFilter !== null && presetGroupFilter !== undefined) {
                this.presetGroupFilter = presetGroupFilter;
            }
            if (presetTagFilter !== null && presetTagFilter !== undefined) {
                this.presetTagFilter = presetTagFilter;
            }

            try {
                const stored = JSON.parse(localStorage.getItem(this.storageKey) || '{}');

                if (typeof stored.open === 'boolean') this.open = stored.open;
                if (typeof stored.width === 'number') this.width = Math.min(this.maxWidth, Math.max(this.minWidth, stored.width));
                if (typeof stored.height === 'number') this.height = Math.min(this.maxHeight, Math.max(this.minHeight, stored.height));
                if (typeof stored.left === 'number') this.left = stored.left;
                if (typeof stored.top === 'number') this.top = stored.top;
                if (typeof stored.autoCenterOnFirstOpen === 'boolean') this.autoCenterOnFirstOpen = stored.autoCenterOnFirstOpen;
                if (typeof stored.hasOpenedOnce === 'boolean') this.hasOpenedOnce = stored.hasOpenedOnce;
                if (typeof stored.showTerminalTools === 'boolean') this.showTerminalTools = stored.showTerminalTools;
                if (typeof stored.historyFilter === 'string') this.historyFilter = stored.historyFilter;
                if (typeof stored.presetSearch === 'string') this.presetSearch = stored.presetSearch;
                if (typeof stored.presetGroupFilter === 'string') this.presetGroupFilter = stored.presetGroupFilter;
                if (typeof stored.presetTagFilter === 'string') this.presetTagFilter = stored.presetTagFilter;
            } catch (error) {}

            this.syncViewport();

            if (!this.isMobile && (!this.left || !this.top)) {
                this.centerModal();
            }

            this.$watch('open', () => this.persist());
            this.$watch('width', () => this.persist());
            this.$watch('height', () => this.persist());
            this.$watch('left', () => this.persist());
            this.$watch('top', () => this.persist());
            this.$watch('autoCenterOnFirstOpen', () => this.persist());
            this.$watch('hasOpenedOnce', () => this.persist());
            this.$watch('showTerminalTools', () => this.persist());
            this.$watch('historyFilter', () => this.persist());
            this.$watch('presetSearch', () => this.persist());
            this.$watch('presetGroupFilter', () => this.persist());
            this.$watch('presetTagFilter', () => this.persist());

            window.addEventListener('verity-open-server-terminal', () => this.openModal());
            window.addEventListener('verity-close-server-terminal', () => this.closeModal());
            window.addEventListener('resize', () => this.syncViewport());
            window.addEventListener('beforeunload', () => this.closeSession());
            window.addEventListener('keydown', (event) => {
                if (!this.open || !event.shiftKey || String(event.key || '').toLowerCase() !== 'c') {
                    return;
                }

                const target = event.target;
                if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
                    return;
                }

                event.preventDefault();
                this.centerModal();
                this.persist();
            });

            this.$nextTick(() => {
                this.bootTerminal();
                this.openSession().finally(() => {
                    this.connectBridge?.();
                    if (!this.sessionHeartbeatTimer && this.sessionHeartbeatUrl) {
                        this.sessionHeartbeatTimer = window.setInterval(() => this.heartbeatSession(), 15000);
                    }
                });
            });
        },
        syncViewport() {
            this.isMobile = window.matchMedia('(max-width: 1023px)').matches;
            if (this.isMobile) {
                this.showMobileTools = false;
            }
            if (this.open && !this.isMobile) {
                this.clampPosition();
            }
        },
        persist() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify({
                    open: this.open,
                    width: this.width,
                    height: this.height,
                    left: this.left,
                    top: this.top,
                    autoCenterOnFirstOpen: this.autoCenterOnFirstOpen,
                    hasOpenedOnce: this.hasOpenedOnce,
                    showTerminalTools: this.showTerminalTools,
                    historyFilter: this.historyFilter,
                    presetSearch: this.presetSearch,
                    presetGroupFilter: this.presetGroupFilter,
                    presetTagFilter: this.presetTagFilter,
                }));
            } catch (error) {}
        },
        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        async openSession() {
            if (this.sessionId && this.bridgeUrl) {
                return this.sessionId;
            }

            try {
                if (!this.sessionOpenUrl) {
                    return null;
                }

                const response = await fetch(this.sessionOpenUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    return null;
                }

                const payload = await response.json();
                this.sessionId = payload?.session?.id ?? this.sessionId;
                this.bridgeUrl = payload?.bridge?.enabled ? payload?.bridge?.url ?? null : null;
                this.bridgeState = this.bridgeUrl ? 'connecting' : 'disabled';
                return this.sessionId;
            } catch (error) {
                return null;
            }
        },
        async heartbeatSession() {
            if (!this.sessionId || !this.sessionHeartbeatUrl) {
                return;
            }

            try {
                await fetch(this.sessionHeartbeatUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ session_id: this.sessionId }),
                });
            } catch (error) {}
        },
        async closeSession() {
            if (!this.sessionId || !this.sessionCloseUrl) {
                return;
            }

            const sessionId = this.sessionId;
            this.sessionId = null;
            this.disconnectBridge();

            try {
                await fetch(this.sessionCloseUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ session_id: sessionId }),
                });
            } catch (error) {}
        },
        centerModal() {
            this.width = Math.min(this.width, Math.min(this.maxWidth, window.innerWidth - 48));
            this.height = Math.min(this.height, Math.min(this.maxHeight, window.innerHeight - 48));
            this.left = Math.max(24, Math.round((window.innerWidth - this.width) / 2));
            this.top = Math.max(24, Math.round((window.innerHeight - this.height) / 2));
        },
        clampPosition() {
            const padding = 24;
            const maxWidth = Math.min(this.maxWidth, window.innerWidth - padding * 2);
            const maxHeight = Math.min(this.maxHeight, window.innerHeight - padding * 2);
            this.width = Math.min(this.width, maxWidth);
            this.height = Math.min(this.height, maxHeight);
            const maxLeft = Math.max(padding, window.innerWidth - this.width - padding);
            const maxTop = Math.max(padding, window.innerHeight - this.height - padding);
            this.left = Math.min(Math.max(this.left || padding, padding), maxLeft);
            this.top = Math.min(Math.max(this.top || padding, padding), maxTop);
        },
        modalStyle() {
            if (this.isMobile) {
                return 'width: calc(100vw - 1rem); height: calc(100vh - 1rem); left: 0.5rem; top: 0.5rem;';
            }

            return `width: ${this.width}px; height: ${this.height}px; left: ${this.left}px; top: ${this.top}px;`;
        },
        bridgeStatusLabel() {
            if (this.bridgeState === 'reconnecting' && this.bridgeReconnectSeconds > 0) {
                return `bridge reconnecting · ${this.bridgeReconnectSeconds}s`;
            }

            return {
                connected: 'bridge live',
                connecting: 'bridge connecting',
                reconnecting: 'bridge reconnecting',
                offline: 'bridge offline',
                disabled: 'bridge disabled',
            }[this.bridgeState] || 'bridge offline';
        },
        bridgeStatusClasses() {
            return ({
                connected: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
                connecting: 'border-cyan-400/20 bg-cyan-500/10 text-cyan-100',
                reconnecting: 'border-amber-400/20 bg-amber-500/10 text-amber-100',
                offline: 'border-rose-400/20 bg-rose-500/10 text-rose-100',
                disabled: 'border-slate-500/20 bg-slate-500/10 text-slate-300',
            }[this.bridgeState] || 'border-rose-400/20 bg-rose-500/10 text-rose-100');
        },
        bridgeDotClasses() {
            return ({
                connected: 'bg-emerald-300 shadow-[0_0_12px_rgba(110,231,183,0.85)]',
                connecting: 'bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.85)]',
                reconnecting: 'bg-amber-300 shadow-[0_0_12px_rgba(251,191,36,0.85)]',
                offline: 'bg-rose-300 shadow-[0_0_12px_rgba(251,113,133,0.85)]',
                disabled: 'bg-slate-400 shadow-[0_0_10px_rgba(148,163,184,0.45)]',
            }[this.bridgeState] || 'bg-rose-300');
        },
        openModal() {
            this.open = true;
            if (this.autoCenterOnFirstOpen && !this.hasOpenedOnce && !this.isMobile) {
                this.centerModal();
                this.hasOpenedOnce = true;
            } else if (!this.isMobile) {
                this.clampPosition();
            }
            this.persist();
            this.$nextTick(() => {
                window.dispatchEvent(new Event('resize'));
                this.openSession().finally(() => {
                    this.bootTerminal();
                    this.connectBridge?.();
                    window.dispatchEvent(new Event('verity-focus-server-terminal'));
                    if (!this.sessionHeartbeatTimer && this.sessionHeartbeatUrl) {
                        this.sessionHeartbeatTimer = window.setInterval(() => this.heartbeatSession(), 15000);
                    }
                });
            });
        },
        closeModal() {
            this.open = false;
            this.showMobileTools = false;
            if (this.sessionHeartbeatTimer) {
                clearInterval(this.sessionHeartbeatTimer);
                this.sessionHeartbeatTimer = null;
            }
            this.closeSession();
            this.persist();
        },
        toggleModal() {
            this.open = !this.open;
            this.persist();
            if (this.open) {
                this.$nextTick(() => {
                    window.dispatchEvent(new Event('resize'));
                    this.openSession().finally(() => {
                        this.bootTerminal();
                        this.connectBridge?.();
                        window.dispatchEvent(new Event('verity-focus-server-terminal'));
                        if (!this.sessionHeartbeatTimer && this.sessionHeartbeatUrl) {
                            this.sessionHeartbeatTimer = window.setInterval(() => this.heartbeatSession(), 15000);
                        }
                    });
                });
            }
        },
        resetWorkspace() {
            this.open = true;
            this.showMobileTools = false;
            this.showTerminalTools = false;
            this.historyFilter = 'all';
            this.presetSearch = '';
            this.presetGroupFilter = '';
            this.presetTagFilter = '';
            this.hasOpenedOnce = true;
            this.centerModal();
            this.persist();

            if (this.terminalApi?.resetTerminalBuffer) {
                this.terminalApi.resetTerminalBuffer();
            }

            if (typeof this.$wire?.resetTerminalWorkspace === 'function') {
                this.$wire.resetTerminalWorkspace();
            }

            this.$nextTick(() => {
                window.dispatchEvent(new Event('resize'));
                this.openSession().finally(() => {
                    this.bootTerminal();
                    this.connectBridge?.();
                    window.dispatchEvent(new Event('verity-focus-server-terminal'));
                    if (!this.sessionHeartbeatTimer && this.sessionHeartbeatUrl) {
                        this.sessionHeartbeatTimer = window.setInterval(() => this.heartbeatSession(), 15000);
                    }
                });
            });
        },
        bootTerminal() {
            if (this.terminalReady || !this.open) return;

            if (typeof window.verityServerTerminal !== 'function') {
                this.terminalBootAttempts += 1;
                if (this.terminalBootAttempts <= 240) {
                    window.setTimeout(() => this.bootTerminal(), 50);
                }
                return;
            }

            this.terminalApi = window.verityServerTerminal({
                componentId: this.componentId,
                feedUrl: this.feedUrl,
                promptText: this.promptText,
                sessionId: this.sessionId,
            });

            Object.assign(this, this.terminalApi);

            this.$nextTick(() => {
                this.init?.();
                this.terminalReady = true;
                this.connectBridge?.();
                window.dispatchEvent(new Event('verity-focus-server-terminal'));
            });
        },
        beginDrag(event) {
            if (this.isMobile) return;
            event.preventDefault();
            const startX = event.clientX;
            const startY = event.clientY;
            const startLeft = this.left;
            const startTop = this.top;
            document.body.classList.add('select-none', 'cursor-move');
            const onMove = (moveEvent) => {
                this.left = Math.max(16, startLeft + (moveEvent.clientX - startX));
                this.top = Math.max(16, startTop + (moveEvent.clientY - startY));
                this.persist();
            };
            const onUp = () => {
                document.body.classList.remove('select-none', 'cursor-move');
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp, { once: true });
        },
        beginResize(event) {
            if (this.isMobile) return;
            event.preventDefault();
            const startX = event.clientX;
            const startY = event.clientY;
            const startWidth = this.width;
            const startHeight = this.height;
            document.body.classList.add('select-none', 'cursor-nwse-resize');
            const onMove = (moveEvent) => {
                this.width = Math.min(this.maxWidth, Math.max(this.minWidth, startWidth + (moveEvent.clientX - startX)));
                this.height = Math.min(this.maxHeight, Math.max(this.minHeight, startHeight + (moveEvent.clientY - startY)));
                this.clampPosition();
                this.persist();
                window.dispatchEvent(new Event('resize'));
            };
            const onUp = () => {
                document.body.classList.remove('select-none', 'cursor-nwse-resize');
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };
            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp, { once: true });
        },
        beginWidthResize(event) {
            if (this.isMobile) return;

            event.preventDefault();

            const startX = event.clientX;
            const startWidth = this.width;

            document.body.classList.add('select-none', 'cursor-ew-resize');

            const onMove = (moveEvent) => {
                this.width = Math.min(this.maxWidth, Math.max(this.minWidth, startWidth + (moveEvent.clientX - startX)));
                this.clampPosition();
                this.persist();
                window.dispatchEvent(new Event('resize'));
            };

            const onUp = () => {
                document.body.classList.remove('select-none', 'cursor-ew-resize');
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp, { once: true });
        },
        beginHeightResize(event) {
            if (this.isMobile) return;

            event.preventDefault();

            const startY = event.clientY;
            const startHeight = this.height;

            document.body.classList.add('select-none', 'cursor-ns-resize');

            const onMove = (moveEvent) => {
                this.height = Math.min(this.maxHeight, Math.max(this.minHeight, startHeight + (moveEvent.clientY - startY)));
                this.clampPosition();
                this.persist();
                window.dispatchEvent(new Event('resize'));
            };

            const onUp = () => {
                document.body.classList.remove('select-none', 'cursor-ns-resize');
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp, { once: true });
        },
        connectBridge() {
            this.terminalApi?.connectBridge?.();
        },
        disconnectBridge() {
            this.terminalApi?.disconnectBridge?.();
        },
    };
};
