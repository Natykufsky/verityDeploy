@php
    $preview = $record->live_configuration_preview;
    $drift = $preview['drift'] ?? [];
@endphp

<div class="space-y-4">
    <div class="deployment-frost-card rounded-3xl p-5">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-300">
            repair drift
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold tracking-tight text-white">
                {{ $record->primary_domain ?? 'no primary domain configured yet' }}
            </h3>
            <x-info-tooltip text="This runs cPanel provisioning steps for domain and ssl, then resyncs the live inventory snapshot." label="Repair help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            This is a write action. It reapplies the cPanel domain and SSL provisioning steps that correspond to the site intent, then refreshes the live inventory snapshot.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>Drift summary</span>
                <x-info-tooltip text="This summarizes the current drift state before the repair runs." label="Drift summary help" />
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-300">{{ $preview['drift']['summary'] ?? 'No drift summary available yet.' }}</p>

            @if (filled($drift['sections'] ?? []))
                <div class="mt-4 space-y-3">
                    @foreach ($drift['sections'] as $section)
                        <div class="rounded-xl border border-white/5 bg-black/20 p-3">
                            <div class="text-[11px] uppercase tracking-[0.2em] text-slate-500">{{ $section['label'] ?? 'Item' }}</div>
                            <div class="mt-2 grid gap-1 text-sm">
                                <div class="text-slate-400">expected</div>
                                <div class="font-semibold text-white">{{ $section['expected'] ?? 'n/a' }}</div>
                                <div class="mt-2 text-slate-400">actual</div>
                                <div class="font-semibold text-white">{{ $section['actual'] ?? 'n/a' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="deployment-frost-panel rounded-2xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                <span>What this will do</span>
                <x-info-tooltip text="The repair action replays the cPanel provisioning steps that match the site's desired state." label="Repair action help" />
            </div>
            <div class="mt-3 space-y-2 text-sm leading-6 text-slate-300">
                <div>reapply domain provisioning when the server can manage domains</div>
                <div>reapply ssl provisioning when the server can manage ssl</div>
                <div>refresh the live inventory snapshot after the repair finishes</div>
            </div>
        </div>
    </div>

    <div class="deployment-frost-panel rounded-2xl p-4">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
            <span>Preview</span>
            <x-info-tooltip text="The preview explains that this is a write action and will update the live state." label="Preview help" />
        </div>
        <p class="mt-2 text-sm leading-6 text-slate-300">
            Use this only when the live cPanel configuration has drifted from the site record and you want the app to reapply the managed settings.
        </p>
    </div>
</div>
