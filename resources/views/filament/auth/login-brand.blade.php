<div class="verity-login-grid grid w-full justify-items-center gap-4 xl:grid-cols-[minmax(18rem,1.05fr)_minmax(22rem,0.95fr)_minmax(18rem,1.05fr)] xl:items-start">
<div class="verity-login-brand mx-auto w-full overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-slate-950 via-slate-950 to-amber-950/35 p-4 text-center shadow-2xl shadow-black/25 md:p-5">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/40 to-transparent"></div>
    <div class="pointer-events-none absolute -right-12 top-6 h-40 w-40 rounded-full bg-amber-500/10 blur-3xl motion-safe:animate-pulse"></div>

    @php
        $branding = app(\App\Services\AppSettings::class);
        $logoUrl = $branding->brandLogoUrl();
    @endphp

    <div class="flex flex-col items-center gap-4 md:flex-row md:items-start md:text-left">
        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-3xl border border-amber-400/20 bg-amber-500/15 shadow-lg shadow-amber-950/20">
            @if (filled($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" class="h-full w-full object-contain p-2.5" />
            @else
                <span class="text-base font-black tracking-[0.24em] text-amber-200">VD</span>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-300">
                {{ $appName }}
            </div>

            <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white md:text-3xl">
                {{ filled($branding->appTagline()) ? $branding->appTagline() : 'Deploy with control, not chaos.' }}
            </h1>
            <p class="mt-2 text-sm leading-7 text-slate-300">
                {{ filled($branding->appDescription()) ? $branding->appDescription() : 'Sign in to manage servers, track deployments, and recover quickly when something needs attention.' }}
            </p>

            <div class="mt-4 flex flex-wrap justify-center gap-3 text-xs text-slate-400 md:justify-start">
                <span class="inline-flex items-center rounded-full border border-white/10 bg-black/20 px-3 py-1 font-medium text-slate-300">
                    Need an invite? Ask your team owner.
                </span>
                <div class="verity-login-status-cycle">
                    <span>Deploys, alerts, and recovery in one place.</span>
                    <span>Track every step from first push to rollback.</span>
                    <span>One dashboard for servers, sites, and operators.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 grid gap-2 sm:grid-cols-3">
        <div class="mx-auto rounded-2xl border border-white/5 bg-black/20 px-4 py-3 text-center text-sm text-slate-300">
            <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Deployments</div>
            <div class="mt-1 font-semibold text-white">Live progress, rollback, and resume tools</div>
        </div>
        <div class="mx-auto rounded-2xl border border-white/5 bg-black/20 px-4 py-3 text-center text-sm text-slate-300">
            <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Servers</div>
            <div class="mt-1 font-semibold text-white">SSH, password, local, and cPanel support</div>
        </div>
        <div class="mx-auto rounded-2xl border border-white/5 bg-black/20 px-4 py-3 text-center text-sm text-slate-300">
            <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Alerts</div>
            <div class="mt-1 font-semibold text-white">Inbox, email, and webhook notifications</div>
        </div>
    </div>

    <div class="mx-auto mt-4 rounded-2xl border border-white/5 bg-black/20 px-4 py-3 text-xs leading-6 text-slate-400">
        <span class="font-semibold text-slate-200">Trust note:</span>
        Sign-ins are restricted to invited accounts, and deployment actions keep a clear recovery trail inside the dashboard.
    </div>
</div>
