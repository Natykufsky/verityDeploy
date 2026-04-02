<div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 text-sm leading-7 text-slate-300 dark:border-white/10">
    <div class="flex items-center gap-2 mb-3">
        <p class="font-semibold text-white">App Configuration Guide</p>
        <x-info-tooltip text="Global settings that control identity, connectivity, and deployment defaults." label="App settings help" />
    </div>
    <p>
        Manage the core configuration of verityDeploy. Update <span class="font-semibold text-white">Branding Settings</span> like the app name and logo, 
        or refine your <span class="font-semibold text-white">Deployment Defaults</span> to set the standard branch and PHP versions for new sites.
        <x-info-tooltip text="These defaults are inherited by new sites unless overridden at the site level." label="Defaults help" />
    </p>
    <p class="mt-3">
        Ensure your <span class="font-semibold text-white">GitHub Integration</span> remains healthy by managing your personal access tokens or OAuth connections. 
        Reliable connectivity is required for automated webhook provisioning and release management.
        <x-info-tooltip text="Use the Connect button to securely authorize GitHub access via OAuth." label="GitHub help" />
    </p>
</div>
