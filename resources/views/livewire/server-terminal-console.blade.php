<div
    x-data="{
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
        terminalBootAttempts: 0,
        autocompleteHint: '',
        historyFilter: @entangle('historyFilter').live,
        presetSearch: @entangle('presetSearch').live,
        presetGroupFilter: @entangle('presetGroupFilter').live,
        presetTagFilter: @entangle('presetTagFilter').live,
        storageKey: @js('verity-server-terminal:' . $server->id),
        initModal() {
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
                this.bootTerminal();
                window.dispatchEvent(new Event('verity-focus-server-terminal'));
            });
        },
        closeModal() {
            this.open = false;
            this.showMobileTools = false;
            this.persist();
        },
        toggleModal() {
            this.open = !this.open;
            this.persist();
            if (this.open) {
                this.$nextTick(() => {
                    window.dispatchEvent(new Event('resize'));
                    this.bootTerminal();
                    window.dispatchEvent(new Event('verity-focus-server-terminal'));
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

            $wire.resetTerminalWorkspace();

            this.$nextTick(() => {
                window.dispatchEvent(new Event('resize'));
                this.bootTerminal();
                window.dispatchEvent(new Event('verity-focus-server-terminal'));
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
                componentId: @js($this->getId()),
                feedUrl: @js(route('servers.terminal-feed', ['record' => $server->id])),
                promptText: @js($terminalPrompt),
            });

            Object.assign(this, this.terminalApi);

            this.$nextTick(() => {
                this.init?.();
                this.terminalReady = true;
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
    }"
    x-init="initModal()"
    x-on:verity-open-server-terminal.window="openModal()"
    x-on:verity-close-server-terminal.window="closeModal()"
    x-on:keydown.escape.window="closeModal()"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="closeModal()"></div>

        <div
            class="fixed overflow-hidden rounded-3xl border border-cyan-400/20 bg-slate-950/96 shadow-[0_30px_100px_-35px_rgba(0,0,0,.95)] backdrop-blur-xl"
            :style="modalStyle()"
        >
            <div class="absolute right-0 top-12 z-30 hidden h-[calc(100%-4rem)] w-5 cursor-ew-resize touch-none bg-transparent hover:bg-cyan-400/10 lg:block" @mousedown.prevent="beginWidthResize" title="Resize terminal width"></div>
            <div class="absolute bottom-0 left-12 z-30 hidden h-5 w-[calc(100%-5rem)] cursor-ns-resize touch-none bg-transparent hover:bg-cyan-400/10 lg:block" @mousedown.prevent="beginHeightResize" title="Resize terminal height"></div>
            <div class="absolute bottom-0 right-0 z-40 hidden h-8 w-8 cursor-nwse-resize rounded-tl-2xl border-l border-t border-white/15 bg-slate-900/70 hover:bg-cyan-400/20 lg:block" @mousedown.prevent="beginResize" title="Resize terminal"></div>

            <div class="flex h-full min-h-0 w-full flex-col">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/5 px-4 py-3.5 lg:px-5 lg:py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @mousedown.prevent="beginDrag"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-500/10 text-cyan-100 hover:bg-cyan-400/20"
                                title="Drag terminal"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M8 6h1M8 12h1M8 18h1M15 6h1M15 12h1M15 18h1" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                                </svg>
                            </button>
                            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-100">
                                Server terminal
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2.5">
                            <h3 class="text-lg font-semibold tracking-tight text-white lg:text-xl">{{ $server->name }}</h3>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">{{ $server->connection_type }}</span>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">{{ $terminalPrompt }}</span>
                        </div>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                            A draggable xterm.js console. Use the sidebar for presets or type commands directly into the terminal.
                        </p>
                    </div>

                    <div class="hidden items-center gap-2 lg:flex">
                        <button type="button" @click="showTerminalTools = !showTerminalTools; persist()" class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span x-show="showTerminalTools" x-cloak>Tools: on</span>
                            <span x-show="!showTerminalTools" x-cloak>Tools: off</span>
                        </button>
                        <button type="button" @click="autoCenterOnFirstOpen = !autoCenterOnFirstOpen; persist()" class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span x-show="autoCenterOnFirstOpen" x-cloak>Auto-center first open: on</span>
                            <span x-show="!autoCenterOnFirstOpen" x-cloak>Auto-center first open: off</span>
                        </button>
                        <button type="button" @click="toggleModal()" class="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span x-show="open" x-cloak>Minimize</span>
                            <span x-show="!open" x-cloak>Maximize</span>
                        </button>
                        <button type="button" @click="centerModal(); persist()" class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100 hover:bg-cyan-400/20">
                            Snap center
                        </button>
                        <button type="button" @click="resetWorkspace()" class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-amber-100 hover:bg-amber-400/20">
                            Reset workspace
                        </button>
                        <button type="button" @click="closeModal()" class="rounded-full border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-rose-100 hover:bg-rose-500/20">
                            Close
                        </button>
                    </div>

                    <details x-ref="mobileActions" class="relative lg:hidden">
                        <summary class="list-none rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                            <span class="inline-flex items-center gap-2">
                                <span>Actions</span>
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] leading-none" :class="showTerminalTools ? 'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' : 'border-slate-500/30 bg-slate-500/10 text-slate-300'" x-text="showTerminalTools ? 'on' : 'off'"></span>
                            </span>
                        </summary>
                        <div class="absolute right-0 z-30 mt-2 w-[18rem] rounded-2xl border border-white/10 bg-slate-950/98 p-2 shadow-[0_20px_60px_-30px_rgba(0,0,0,.95)] backdrop-blur-xl">
                            <div class="grid gap-2">
                                <button type="button" @click="showTerminalTools = !showTerminalTools; persist(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                                    <span x-show="showTerminalTools" x-cloak>Tools: on</span>
                                    <span x-show="!showTerminalTools" x-cloak>Tools: off</span>
                                </button>
                                <button type="button" @click="autoCenterOnFirstOpen = !autoCenterOnFirstOpen; persist(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                                    <span x-show="autoCenterOnFirstOpen" x-cloak>Auto-center first open: on</span>
                                    <span x-show="!autoCenterOnFirstOpen" x-cloak>Auto-center first open: off</span>
                                </button>
                                <button type="button" @click="toggleModal(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-200 hover:bg-white/10">
                                    <span x-show="open" x-cloak>Minimize</span>
                                    <span x-show="!open" x-cloak>Maximize</span>
                                </button>
                                <button type="button" @click="centerModal(); persist(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-cyan-400/20 bg-cyan-500/10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100 hover:bg-cyan-400/20">
                                    Snap center
                                </button>
                                <button type="button" @click="resetWorkspace(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-amber-100 hover:bg-amber-400/20">
                                    Reset workspace
                                </button>
                                <button type="button" @click="closeModal(); $nextTick(() => $refs.mobileActions?.removeAttribute('open'))" class="rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-rose-100 hover:bg-rose-500/20">
                                    Close
                                </button>
                            </div>
                        </div>
                    </details>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="grid gap-4 p-4 transition-all duration-300" :class="showTerminalTools ? 'lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]' : 'lg:grid-cols-1'">
                    <aside
                        x-show="showTerminalTools"
                        x-cloak
                        x-transition:enter="transition ease-out duration-220"
                        x-transition:enter-start="opacity-0 -translate-x-3 scale-95"
                        x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                        x-transition:leave="transition ease-in duration-160"
                        x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                        x-transition:leave-end="opacity-0 -translate-x-2 scale-95"
                        class="space-y-4 overflow-hidden lg:max-h-[calc(100vh-16rem)] lg:overflow-y-auto lg:pr-1 lg:origin-left"
                    >
                        <div class="rounded-2xl border border-white/5 bg-black/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">History filter</div>
                                    <p class="mt-1 text-sm text-slate-400">Narrow the recent command cards below.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $runsCount }} runs</div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($historyFilterOptions as $value => $label)
                                    <button type="button" x-on:click="historyFilter = @js($value)" @class([
                                        'rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.2em]',
                                        'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => $historyFilter === $value,
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $historyFilter !== $value,
                                    ])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-white/5 bg-black/40 p-4">
                            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Quick commands</div>
                            <p class="mt-1 text-sm text-slate-400">One-click diagnostics for this server.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($quickCommands as $quickCommand)
                                    <button type="button" wire:click="executeQuickCommand(@js($quickCommand['command']))" class="group inline-flex flex-col items-start rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 text-left hover:border-cyan-400/40 hover:bg-slate-900">
                                        <span class="text-sm font-semibold text-white group-hover:text-cyan-200">{{ $quickCommand['label'] }}</span>
                                        <span class="mt-1 text-xs text-slate-400">{{ $quickCommand['description'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Shell presets</div>
                                    <p class="mt-1 text-sm text-slate-300">Save reusable snippets and organize them by folder or tags.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ collect($presetGroups)->sum(fn ($group) => is_countable($group['presets'] ?? null) ? count($group['presets']) : 0) }} saved</div>
                            </div>
                            <div class="mt-3 grid gap-2">
                                <input x-model="presetSearch" type="search" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Search presets, folders, or tags" />
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-on:click="presetGroupFilter = ''" @class([
                                        'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                        'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => blank($presetGroupFilter),
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => filled($presetGroupFilter),
                                    ])>All folders</button>
                                    @foreach ($presetGroupOptions as $groupOption)
                                        <button type="button" x-on:click="presetGroupFilter = @js($groupOption)" @class([
                                            'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                            'border-cyan-400/30 bg-cyan-500/15 text-cyan-100' => $presetGroupFilter === $groupOption,
                                            'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $presetGroupFilter !== $groupOption,
                                        ])>{{ $groupOption }}</button>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-on:click="presetTagFilter = ''" @class([
                                        'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                        'border-amber-400/30 bg-amber-500/15 text-amber-100' => blank($presetTagFilter),
                                        'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => filled($presetTagFilter),
                                    ])>All tags</button>
                                    @foreach ($presetTagOptions as $tagOption)
                                        <button type="button" x-on:click="presetTagFilter = @js($tagOption)" @class([
                                            'rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.2em]',
                                            'border-amber-400/30 bg-amber-500/15 text-amber-100' => $presetTagFilter === $tagOption,
                                            'border-white/10 bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white' => $presetTagFilter !== $tagOption,
                                        ])>#{{ $tagOption }}</button>
                                    @endforeach
                                </div>
                                <input wire:model.live.debounce.250ms="presetName" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Preset name" />
                                <input wire:model.live.debounce.250ms="presetGroup" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Folder / group (optional)" />
                                <input wire:model.live.debounce.250ms="presetTags" type="text" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Tags, comma separated" />
                                <textarea wire:model.live.debounce.250ms="presetDescription" rows="2" class="block w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" placeholder="Optional description"></textarea>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="savePreset" class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400">{{ $editingPresetId ? 'Update preset' : 'Save preset' }}</button>
                                <button type="button" wire:click="resetPresetForm" class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">Clear</button>
                                <button type="button" wire:click="resetPresetFilters" class="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-2.5 text-sm font-semibold text-amber-100 hover:bg-amber-400/20">Reset filters</button>
                            </div>
                        </div>
                    </aside>

                    <section class="flex min-h-0 flex-col gap-4">
                        <div class="min-h-0 flex-1 rounded-3xl border border-white/5 bg-slate-950/90 p-4 shadow-[0_24px_80px_-32px_rgba(0,0,0,.8)]">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-cyan-200/70">xterm.js terminal</div>
                                    <p class="mt-1 text-sm text-slate-400">Type commands directly into the terminal. Live output streams while queued jobs run in the background.</p>
                                </div>
                                <div class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100">
                                    <span x-show="terminalReady" x-cloak>Live streaming</span>
                                    <span x-show="!terminalReady" x-cloak>Loading terminal...</span>
                                </div>
                            </div>

                            <div wire:ignore x-show="open" class="mt-4 min-h-0 flex-1">
                                <div x-ref="terminal" class="h-full min-h-[26rem] w-full overflow-hidden rounded-2xl border border-white/5 bg-slate-950"></div>
                                <div x-show="autocompleteHint" x-cloak class="mt-3 rounded-xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100" x-text="autocompleteHint"></div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-3xl border border-white/5 bg-black/40 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Recent command cards</div>
                                    <p class="mt-1 text-sm text-slate-400">A compact record view stays below the terminal for quick scanning.</p>
                                </div>
                                <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $runsCount }} runs</div>
                            </div>

                            <div class="grid gap-3 xl:grid-cols-2">
                                @forelse ($runs as $run)
                                    <details @class([
                                        'group rounded-2xl border border-white/5 bg-slate-900/70 px-4 py-4 transition-all duration-300',
                                        'border-cyan-400/30 bg-cyan-400/10 shadow-[0_0_0_1px_rgba(34,211,238,0.2),0_0_30px_rgba(14,165,233,0.12)] ring-1 ring-cyan-400/25' => $run['status'] === 'running',
                                    ]) {{ $run['status'] === 'running' ? 'open' : '' }}>
                                        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 text-[11px] uppercase tracking-[0.24em] text-slate-400">
                                            <span class="inline-flex items-center gap-2">
                                                @if ($run['status'] === 'running')<span class="inline-flex h-2.5 w-2.5 rounded-full bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.9)] animate-pulse"></span>@endif
                                                <span>[{{ $run['started_at']?->format('H:i:s') ?? '--:--:--' }}]</span>
                                            </span>
                                            <span class="text-cyan-300">$</span>
                                            <span class="font-semibold text-white">{{ $run['command'] }}</span>
                                            <span @class([
                                                'rounded-full px-2.5 py-1 font-semibold',
                                                'bg-emerald-500/15 text-emerald-300' => $run['status'] === 'successful',
                                                'bg-cyan-500/15 text-cyan-300' => $run['status'] === 'running',
                                                'bg-rose-500/15 text-rose-300' => $run['status'] === 'failed',
                                                'bg-slate-500/15 text-slate-300' => ! in_array($run['status'], ['successful', 'running', 'failed'], true),
                                            ])>{{ $run['status'] }}</span>
                                            <span class="text-slate-500">{{ $run['duration_label'] }}</span>
                                        </summary>

                                        <div class="mt-3 rounded-xl border border-white/5 bg-black/50 px-4 py-3">
                                            <div class="space-y-2 text-sm text-slate-300">
                                                <div><span class="font-semibold text-slate-100">Exit code:</span> {{ $run['exit_code'] ?? 'pending' }}</div>
                                                @if (filled($run['error_message']))
                                                    <div class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-rose-100">{{ $run['error_message'] }}</div>
                                                @endif
                                            </div>

                                            @if (filled($run['output']))
                                                <pre class="mt-3 whitespace-pre-wrap break-words rounded-xl border border-white/5 bg-slate-950 px-4 py-3 font-mono text-xs leading-6 text-slate-100">{{ $run['output'] }}</pre>
                                            @else
                                                <div class="mt-3 text-slate-500">No terminal output yet.</div>
                                            @endif
                                        </div>
                                    </details>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-cyan-400/20 bg-black/30 px-4 py-6 text-slate-400">No terminal commands have run yet. Use the terminal or the quick commands to start a session.</div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
