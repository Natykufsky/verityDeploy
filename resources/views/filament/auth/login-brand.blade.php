<div class="verity-login-brand mx-auto w-full overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-slate-950 via-slate-900 to-amber-950/40 p-6 text-center shadow-2xl shadow-black/30 md:p-8">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/50 to-transparent"></div>
    <div class="pointer-events-none absolute -right-12 top-6 h-40 w-40 rounded-full bg-amber-500/15 blur-3xl motion-safe:animate-pulse"></div>

    @php
        $branding = app(\App\Services\AppSettings::class);
        $logoUrl = $branding->brandLogoUrl();
        $appName = $branding->appName();
    @endphp

    <div class="flex flex-col items-center gap-6 md:flex-row md:items-start md:text-left">
        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-3xl border border-amber-400/30 bg-amber-500/20 shadow-lg shadow-amber-950/30">
            @if (filled($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" class="h-full w-full object-contain p-3" />
            @else
                <span class="text-lg font-black tracking-[0.25em] text-amber-200">VD</span>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-300">
                {{ $appName }}
            </div>

            <h1 class="mt-4 text-3xl font-bold tracking-tight text-white md:text-4xl">
                {{ filled($branding->appTagline()) ? $branding->appTagline() : 'Deploy with control, not chaos.' }}
            </h1>
            <p class="mt-3 text-base leading-7 text-slate-300">
                {{ filled($branding->appDescription()) ? $branding->appDescription() : 'Sign in to manage servers, track deployments, and recover quickly when something needs attention.' }}
            </p>

            <div class="mt-5 flex flex-wrap justify-center gap-4 text-sm text-slate-400 md:justify-start">
                <span class="inline-flex items-center rounded-full border border-white/10 bg-black/30 px-4 py-2 font-medium text-slate-300">
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
</div>
