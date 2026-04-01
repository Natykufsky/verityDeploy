@php
    $statusColor = match ($record->status) {
        'online' => 'success',
        'offline' => 'gray',
        'error' => 'danger',
        default => 'warning',
    };

    $connectionColor = match ($record->connection_type) {
        'ssh_key' => 'success',
        'password' => 'warning',
        'local' => 'gray',
        'cpanel' => 'info',
        default => 'gray',
    };
@endphp

<details open class="deployment-frost-card rounded-3xl p-5">
    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
                <span class="h-2 w-2 rounded-full bg-cyan-300"></span>
                server overview
                <x-info-tooltip text="Infrastructure identity, connection mode, and provider state for this server." label="Server overview help" />
            </div>
            <div class="space-y-2">
                <h2 class="text-2xl font-semibold tracking-tight text-white md:text-3xl">{{ $record->name }}</h2>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ $statusColor === 'success' ? 'bg-emerald-500/15 text-emerald-300' : ($statusColor === 'danger' ? 'bg-rose-500/15 text-rose-300' : ($statusColor === 'warning' ? 'bg-amber-500/15 text-amber-300' : 'bg-white/5 text-slate-300')) }}">
                {{ $record->status }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] {{ $connectionColor === 'success' ? 'bg-emerald-500/15 text-emerald-300' : ($connectionColor === 'danger' ? 'bg-rose-500/15 text-rose-300' : ($connectionColor === 'warning' ? 'bg-amber-500/15 text-amber-300' : ($connectionColor === 'info' ? 'bg-sky-500/15 text-sky-300' : 'bg-white/5 text-slate-300'))) }}">
                {{ $record->connection_type }}
            </span>
            <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-300">
                {{ $record->provider_label }}
            </span>
        </div>
    </summary>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Owner</span>
                <x-info-tooltip text="The owner is the primary user account, while the team is the shared access group if one is assigned." label="Owner help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->owner?->name ?? 'Unassigned' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->team?->name ?? 'No team assigned' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Connection</span>
                <x-info-tooltip text="Connection mode controls how the app reaches the server: SSH key, password, local host, or cPanel." label="Connection help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ ucfirst((string) $record->connection_type) }}</div>
            <div class="mt-1 text-sm text-slate-400">SSH user: {{ $record->ssh_user ?? 'n/a' }}</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Last connected</span>
                <x-info-tooltip text="This shows when the server last responded successfully to a connection or health check." label="Last connected help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->last_connected_at?->format('M d, Y H:i') ?? 'Never' }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->connectionTests()->count() }} connection checks recorded</div>
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Provider</span>
                <x-info-tooltip text="The provider tells the app which platform or host manages this server." label="Provider help" />
            </div>
            <div class="mt-2 text-sm font-semibold text-white">{{ $record->provider_label }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ $record->provider_region ?: 'No region set' }}</div>
        </div>
    </div>
</details>
