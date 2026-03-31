<div class="relative flex h-[70vh] max-h-[70vh] min-h-0 flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl shadow-black/50">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(251,191,36,0.14),transparent_28%),radial-gradient(circle_at_top_right,rgba(56,189,248,0.12),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01))]"></div>
    <div class="pointer-events-none absolute inset-0 backdrop-blur-[2px]"></div>

    <div class="relative z-10 flex-1 min-h-0 space-y-6 overflow-y-auto p-4 pr-1">
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4 text-slate-100 shadow-lg shadow-black/20 backdrop-blur-md">
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Server checks</p>
                <h3 class="mt-1 text-lg font-semibold text-white">{{ $record->name }}</h3>
                <p class="mt-1 break-all text-sm text-slate-300">
                    {{ $record->ssh_user }}@{{ $record->ip_address }}:{{ $record->ssh_port ?? 22 }}
                </p>
            </div>

            <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $record->status === 'online' ? 'bg-emerald-500/15 text-emerald-300' : ($record->status === 'error' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300') }}">
                {{ $record->status }}
            </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-slate-300 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Last connected</p>
                <p class="mt-1 font-medium text-white">{{ $record->last_connected_at?->format('Y-m-d H:i:s') ?? 'Never' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Recent checks</p>
                <p class="mt-1 font-medium text-white">{{ $record->connectionTests->count() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Connection type</p>
                <p class="mt-1 font-medium text-white">{{ $record->connection_type }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">SSH key</p>
                <p class="mt-1 font-medium text-white">{{ filled($record->ssh_key) ? 'configured' : 'missing' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">cPanel API token</p>
                <p class="mt-1 font-medium text-white">{{ filled($record->cpanel_api_token) ? 'configured' : 'missing' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">cPanel API port</p>
                <p class="mt-1 font-medium text-white">{{ $record->cpanel_api_port ?? 2083 }}</p>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Recent check results</h4>

        <div class="max-h-[380px] overflow-y-auto pr-1">
            @forelse ($record->connectionTests->take(5) as $test)
                <div class="mb-3 rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg shadow-black/10 backdrop-blur-md">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $test->tested_at?->format('Y-m-d H:i:s') ?? 'Pending' }}</p>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $test->command }}</p>
                        </div>
                        <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $test->status === 'successful' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' }}">
                            {{ $test->status }}
                        </div>
                    </div>

                    <div class="mt-3 max-h-[180px] overflow-y-auto rounded-xl border border-white/5 bg-black/25 p-3 font-mono text-xs leading-6 text-slate-100">
                        <pre class="whitespace-pre-wrap break-words">{{ $test->output ?: $test->error_message ?: 'No output captured.' }}</pre>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-6 text-sm text-slate-400">
                    No checks have been recorded yet.
                </div>
            @endforelse
        </div>
    </div>

    @if ($record->connection_type === 'password')
        @php
            $latestError = optional($record->connectionTests->firstWhere('status', 'failed'))->error_message;
            $portState = filled($record->ssh_port) ? 'configured' : 'missing';
            $passwordState = filled($record->sudo_password) ? 'saved' : 'missing';
            $policyState = filled($latestError)
                ? (str_contains(strtolower($latestError), 'permission denied')
                    ? 'blocked'
                    : (str_contains(strtolower($latestError), 'timeout')
                        ? 'timeout'
                        : 'unknown'))
                : 'needs test';
        @endphp

        <div x-data="{ open: false }" class="space-y-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 shadow-lg shadow-black/10 backdrop-blur-md">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.22em] text-amber-300">Password checklist</p>
                    <h4 class="mt-1 text-sm font-semibold text-amber-50">Port / policy / password</h4>
                </div>
                <button
                    type="button"
                    @click="open = !open"
                    class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-100"
                >
                    <span x-show="!open" x-cloak>Show checklist</span>
                    <span x-show="open" x-cloak>Hide checklist</span>
                </button>
            </div>

            <div x-show="open" x-cloak x-transition class="grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-white/5 bg-black/25 p-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Port</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ $portState }}</p>
                    <p class="mt-1 text-xs leading-6 text-slate-300">Confirm the SSH port is correct and the host allows connections on that port.</p>
                </div>
                <div class="rounded-xl border border-white/5 bg-black/25 p-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Policy</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ $policyState }}</p>
                    <p class="mt-1 text-xs leading-6 text-slate-300">If login fails, the host may block password SSH or require a different account policy.</p>
                </div>
                <div class="rounded-xl border border-white/5 bg-black/25 p-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-slate-400">Password</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ $passwordState }}</p>
                    <p class="mt-1 text-xs leading-6 text-slate-300">Make sure the saved SSH password is the login password for the SSH user, not the cPanel API token.</p>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>
