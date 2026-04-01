@props([
    'eyebrow' => 'terminal',
    'title' => null,
    'subtitle' => null,
    'status' => null,
    'statusTone' => 'cyan',
])

@php
    $statusToneClasses = match ($statusTone) {
        'emerald' => 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100',
        'amber' => 'border-amber-400/20 bg-amber-500/10 text-amber-100',
        'rose' => 'border-rose-400/20 bg-rose-500/10 text-rose-100',
        'slate' => 'border-white/10 bg-white/5 text-slate-300',
        default => 'border-cyan-400/20 bg-cyan-500/10 text-cyan-100',
    };
@endphp

<section {{ $attributes->merge(['class' => 'rounded-3xl border border-white/5 bg-slate-950/90 p-4 shadow-[0_24px_80px_-32px_rgba(0,0,0,.8)]']) }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="text-xs uppercase tracking-[0.2em] text-cyan-200/70">{{ strtolower($eyebrow) }}</div>
            @if (filled($title))
                <h3 class="mt-1 text-lg font-semibold tracking-tight text-white lg:text-xl">{{ $title }}</h3>
            @endif
            @if (filled($subtitle))
                <p class="mt-1 text-sm text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if (isset($actions))
                {{ $actions }}
            @endif

            @if (filled($status))
                <span class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] {{ $statusToneClasses }}">
                    {{ $status }}
                </span>
            @endif
        </div>
    </div>

    <div class="mt-4 min-h-0">
        {{ $slot }}
    </div>
</section>
