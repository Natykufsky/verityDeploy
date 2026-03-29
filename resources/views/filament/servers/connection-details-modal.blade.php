<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Server checks</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $record->name }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $record->ssh_user }}@{{ $record->ip_address }}:{{ $record->ssh_port ?? 22 }}
                </p>
            </div>

            <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $record->status === 'online' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : ($record->status === 'error' ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300') }}">
                {{ $record->status }}
            </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm text-slate-600 dark:text-slate-400 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Last connected</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->last_connected_at?->format('Y-m-d H:i:s') ?? 'Never' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Recent checks</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->connectionTests->count() }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Connection type</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->connection_type }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">SSH key</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ filled($record->ssh_key) ? 'configured' : 'missing' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">cPanel API token</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ filled($record->cpanel_api_token) ? 'configured' : 'missing' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">cPanel API port</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->cpanel_api_port ?? 2083 }}</p>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Recent check results</h4>

        @forelse ($record->connectionTests->take(5) as $test)
            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $test->tested_at?->format('Y-m-d H:i:s') ?? 'Pending' }}</p>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $test->command }}</p>
                    </div>
                    <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $test->status === 'successful' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/15 text-rose-700 dark:text-rose-300' }}">
                        {{ $test->status }}
                    </div>
                </div>

                <div class="mt-3 rounded-xl bg-slate-50 p-3 font-mono text-xs leading-6 text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <pre class="whitespace-pre-wrap break-words">{{ $test->output ?: $test->error_message ?: 'No output captured.' }}</pre>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500 dark:border-slate-700">
                No checks have been recorded yet.
            </div>
        @endforelse
    </div>
</div>
