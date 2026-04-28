<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>queue workers</span>
            <x-info-tooltip text="Restart the site's Laravel queue workers so new jobs are picked up after a deployment." label="Queue restart help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">php artisan queue:restart</div>
        <p class="mt-1 text-sm text-slate-400">Gracefully asks active queue workers to restart.</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>horizon</span>
            <x-info-tooltip text="Terminate Horizon so the daemon or supervisor can relaunch it cleanly." label="Horizon help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">php artisan horizon:terminate</div>
        <p class="mt-1 text-sm text-slate-400">Stops Horizon without killing the entire server.</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>supervisor</span>
            <x-info-tooltip text="Restart the process supervisor that keeps long-running worker processes alive." label="Supervisor help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">supervisorctl restart all</div>
        <p class="mt-1 text-sm text-slate-400">Useful when the worker daemon itself is stuck or stale.</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>daemon status</span>
            <x-info-tooltip text="Check whether supervisor, Horizon, and queue workers are actually running on the server." label="Daemon status help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">Check daemon status</div>
        <p class="mt-1 text-sm text-slate-400">Use the header action to scan the live daemon state and record the output.</p>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>daemon recovery</span>
            <x-info-tooltip text="Try to recover the stack by restarting supervisor, Horizon, and queue workers." label="Daemon recovery help" />
        </div>
        <div class="mt-2 text-sm font-semibold text-white">Recover daemon stack</div>
        <p class="mt-1 text-sm text-slate-400">Use the header action to attempt a full recovery pass when the status check reports trouble.</p>
    </div>
</div>

<div class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4 text-sm text-slate-300">
    Use the header <span class="font-semibold text-white">Processes</span> actions to run these commands against the current release.
</div>
