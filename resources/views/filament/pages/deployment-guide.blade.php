<div class="rounded-2xl border border-slate-200/10 bg-slate-950/70 p-5 text-sm leading-7 text-slate-300 dark:border-white/10">
    <div class="flex items-center gap-2 mb-3">
        <p class="font-semibold text-white">Deployment Lifecycle Guide</p>
        <x-info-tooltip text="Learn how to monitor and recover this specific deployment record." label="Deployment guide help" />
    </div>
    <p>
        This page tracks the entire lifecycle of a deployment record. Use the <span class="font-semibold text-white">Overview</span> tab for a quick 
        summary of the <span class="font-semibold text-white">Latest State</span> and timing metrics.
        <x-info-tooltip text="Elapsed time metrics capture the full duration from start to finish." label="Timing help" />
    </p>
    <p class="mt-3">
        The <span class="font-semibold text-white">Progress</span> tab shows the step-by-step success of each deployment command. If a failure occurs, 
        visit the <span class="font-semibold text-white">Recovery</span> tab for guidance or use the <span class="font-semibold text-white">Resume</span> 
        header action to continue where you left off.
        <x-info-tooltip text="Resumes continue from the next incomplete step, skipping completed ones." label="Resume guidance help" />
    </p>
    <p class="mt-3">
        Toggle the <span class="font-semibold text-white">Logs</span> tab for live terminal output while the deployment is running, or 
        visit the <span class="font-semibold text-white">Commands</span> guide to find useful manual copy-paste snippets.
        <x-info-tooltip text="Terminal output is live-synced using server-side logs during the run." label="Live logs help" />
    </p>
</div>
