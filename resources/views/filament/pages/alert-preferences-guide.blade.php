<div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 text-sm leading-7 text-slate-300 dark:border-white/10">
    <div class="flex items-center gap-2 mb-3">
        <p class="font-semibold text-white">Alert Preferences Guide</p>
        <x-info-tooltip text="Choose which operational events and delivery channels matter most to you." label="Alerts help" />
    </div>
    <p>
        Stay informed about your infrastructure by choosing the right delivery channels. You can toggle <span class="font-semibold text-white">In-App Alerts</span> for 
        the dashboard inbox, and enable <span class="font-semibold text-white">Email Notifications</span> for offline monitoring.
        <x-info-tooltip text="Email alerts are summarized and sent periodically or during critical platform failures." label="Email alert help" />
    </p>
    <p class="mt-3">
        Set the <span class="font-semibold text-white">Minimum Severity Level</span> to filter out background noise. Higher levels like 
        <span class="text-rose-300 font-semibold italic">Danger</span> will only alert you during critical failures.
        <x-info-tooltip text="Warning alerts include transient failures, while Danger alerts require manual intervention." label="Severity help" />
    </p>
</div>
