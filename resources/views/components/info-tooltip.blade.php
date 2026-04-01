@props([
    'text',
    'label' => 'More info',
])

<span
    class="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 transition hover:border-cyan-400/30 hover:bg-cyan-500/10 hover:text-cyan-100"
    title="{{ $text }}"
    aria-label="{{ $label }}"
>
    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M10 14v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        <path d="M10 6h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        <circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.5" />
    </svg>
</span>
