<x-filament-panels::page.simple>
    <div class="grid gap-8 lg:grid-cols-[minmax(24rem,1fr)_minmax(28rem,1fr)] xl:gap-10">
        <div class="space-y-8">
            @include('filament.auth.login-brand')
            @include('filament.auth.login-help')
        </div>

        <div class="mx-auto w-full max-w-xl rounded-3xl border border-white/10 bg-slate-950/80 p-8 shadow-2xl shadow-black/40 ring-1 ring-white/10 sm:p-10">
            <div class="mb-8 text-center">
                <div class="text-sm font-semibold uppercase tracking-[0.25em] text-amber-300">Admin sign in</div>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white">Enter your credentials</h2>
                <p class="mt-3 text-sm leading-6 text-slate-400">Access your deployment dashboard, manage servers, and track release health.</p>
            </div>

            <div class="space-y-6">
                {{ $this->content }}
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
