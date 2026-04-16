<div class="space-y-4">
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/60">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Target</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Server</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->server?->name ?? 'No server selected' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">cPanel account</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ $record->server?->ssh_user ?? 'Not set' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Deploy path</p>
                <p class="mt-1 font-mono text-xs text-slate-900 dark:text-slate-100">{{ $record->deploy_path ?? 'Not set' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Source mode</p>
                <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ ucfirst((string) $record->deploy_source) }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
        <p class="font-semibold">What this wizard does</p>
        <ul class="mt-2 list-disc space-y-1 pl-4 leading-6">
            <li>Validates the cPanel API token and account access.</li>
            <li>Creates the workspace directories and shared runtime folders.</li>
            <li>Prepares the site for either Git deployments or local-source uploads.</li>
        </ul>
    </div>

    @if ($record->deploy_source === 'local')
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Local source</p>
            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">Dashboard source packaging</p>
            <p class="mt-2 leading-6">
                The next deployment will package the dashboard source from
                <span class="font-mono text-xs">{{ $record->local_source_archive ?? 'Not configured' }}</span>
                and upload it to the cPanel workspace before extraction.
            </p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Git source</p>
            <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">Version control deployment</p>
            <p class="mt-2 leading-6">
                The next deployment will configure cPanel Version Control and create a deployment task for
                <span class="font-mono text-xs">{{ $record->repository_url ?? 'No repository set' }}</span>.
            </p>
        </div>
    @endif
</div>
