window.verityDeploymentStream = function verityDeploymentStream(options) {
    return {
        componentId: options.componentId,
        refreshMethod: options.refreshMethod || 'refreshFromBridge',
        bridgeUrl: options.bridgeUrl || null,
        bridgeSocket: null,
        bridgeState: options.bridgeUrl ? 'connecting' : 'disabled',
        bridgeReconnectTimer: null,
        bridgeReconnectCountdownTimer: null,
        bridgeReconnectSeconds: 0,
        bridgeToast: '',
        bridgeToastTimer: null,
        lastChecksum: null,
        refreshTimer: null,
        refreshCooldown: null,

        init() {
            this.connectBridge();
        },

        destroy() {
            this.disconnectBridge();
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

        clearReconnectCountdown() {
            if (this.bridgeReconnectCountdownTimer) {
                clearInterval(this.bridgeReconnectCountdownTimer);
                this.bridgeReconnectCountdownTimer = null;
            }

            this.bridgeReconnectSeconds = 0;
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

        connectBridge() {
            if (!this.bridgeUrl) {
                this.setBridgeState('disabled');
                return;
            }

            if (this.bridgeSocket || this.bridgeState === 'connected') {
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
                this.setBridgeState('connected');
                this.clearReconnectCountdown();
                this.setBridgeToast('bridge live');
            });

            this.bridgeSocket.addEventListener('message', (event) => {
                this.handleMessage(event.data);
            });

            this.bridgeSocket.addEventListener('close', () => {
                this.bridgeSocket = null;
                this.setBridgeState(this.bridgeUrl ? 'reconnecting' : 'offline');
                this.setBridgeToast(this.bridgeUrl ? 'bridge reconnecting' : 'bridge offline');
                this.scheduleReconnect();
            });

            this.bridgeSocket.addEventListener('error', () => {
                this.bridgeSocket = null;
                this.setBridgeState('offline');
                this.setBridgeToast('bridge offline');
            });
        },

        scheduleReconnect() {
            if (!this.bridgeUrl || this.bridgeReconnectTimer) {
                return;
            }

            this.setBridgeState('reconnecting');
            this.clearReconnectCountdown();
            this.bridgeReconnectSeconds = 2;
            this.bridgeReconnectCountdownTimer = window.setInterval(() => {
                if (this.bridgeReconnectSeconds > 0) {
                    this.bridgeReconnectSeconds -= 1;
                }

                if (this.bridgeReconnectSeconds <= 0) {
                    this.clearReconnectCountdown();
                }
            }, 1000);

            this.bridgeReconnectTimer = window.setTimeout(() => {
                this.bridgeReconnectTimer = null;
                this.clearReconnectCountdown();
                this.connectBridge();
            }, 2000);
        },

        disconnectBridge() {
            if (this.bridgeReconnectTimer) {
                clearTimeout(this.bridgeReconnectTimer);
                this.bridgeReconnectTimer = null;
            }

            this.clearReconnectCountdown();

            if (this.bridgeToastTimer) {
                clearTimeout(this.bridgeToastTimer);
                this.bridgeToastTimer = null;
            }

            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
                this.refreshTimer = null;
            }

            if (this.bridgeSocket) {
                try {
                    this.bridgeSocket.close(1000, 'deployment closed');
                } catch (error) {}
            }

            this.bridgeSocket = null;
            this.setBridgeState(this.bridgeUrl ? 'offline' : 'disabled');
        },

        queueRefresh() {
            if (this.refreshTimer) {
                return;
            }

            this.refreshTimer = window.setTimeout(() => {
                this.refreshTimer = null;
                this.requestRefresh();
            }, 250);
        },

        requestRefresh() {
            if (!this.componentId || !window.Livewire?.find) {
                return;
            }

            const component = window.Livewire.find(this.componentId);
            if (!component || typeof component.call !== 'function') {
                return;
            }

            try {
                component.call(this.refreshMethod);
            } catch (error) {
                // If Livewire is mid-render, the next snapshot will retry.
            }
        },

        handleMessage(payload) {
            let message = payload;

            try {
                message = JSON.parse(payload);
            } catch (error) {
                return;
            }

            const type = String(message?.type || '');

            if (type === 'ready' || type === 'snapshot') {
                const snapshot = message?.snapshot || {};
                const checksum = String(snapshot?.checksum || snapshot?.deployment?.updated_at || snapshot?.deployment?.id || '');

                if (checksum && checksum !== this.lastChecksum) {
                    this.lastChecksum = checksum;
                    this.queueRefresh();
                }

                return;
            }

            if (type === 'error') {
                this.setBridgeState('offline');
                this.setBridgeToast(String(message?.message || 'bridge error.'));
            }
        },
    };
};
