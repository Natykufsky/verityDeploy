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
        },

        focusTerminal() {
            if (!this.terminal) {
                return;
            }

            window.requestAnimationFrame(() => {
                this.terminal.focus();
            });
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
    };
};
