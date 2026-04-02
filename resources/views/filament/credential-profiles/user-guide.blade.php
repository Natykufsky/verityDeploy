<div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
    <header class="border-b border-gray-200 dark:border-gray-800 pb-4">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Credential Profile Guide</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Securely manage your global API tokens and server access keys.</p>
    </header>

    <div class="grid gap-6">
        <section class="space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-widest text-gray-400">Profile Types</h3>
            
            <div class="space-y-3">
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/5">
                    <strong class="text-gray-900 dark:text-gray-100">SSH access</strong>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Requires <code>username</code>, <code>port</code>, and <code>private_key</code>. Used for direct server connection and deployments.</p>
                </div>
                
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/5">
                    <strong class="text-gray-900 dark:text-gray-100">cPanel API</strong>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Requires an <code>api_token</code>. Enables UAPI integration for domains, SSL, and DNS management.</p>
                </div>

                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/5">
                    <strong class="text-gray-900 dark:text-gray-100">GitHub</strong>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Requires a Personal Access Token (PAT) with <code>repo</code> scope. Used for source code access and deployment hooks.</p>
                </div>

                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/5">
                    <strong class="text-gray-900 dark:text-gray-100">DNS (Cloudflare)</strong>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Requires an <code>api_token</code> and <code>zone_id</code>. Used for automated record provisioning during deployment.</p>
                </div>
            </div>
        </section>

        <section class="p-4 rounded-xl bg-primary-50 dark:bg-primary-950/20 border border-primary-200 dark:border-primary-800/40">
            <h4 class="text-xs font-bold text-primary-900 dark:text-primary-300 flex items-center gap-2">
                <x-heroicon-o-shield-check class="w-4 h-4" />
                Security Warning
            </h4>
            <p class="mt-1 text-xs text-primary-800 dark:text-primary-400 leading-relaxed">
                Tokens are stored securely. When updating a profile, ensure you use the exact field keys (e.g. <code>api_token</code>) expected by the system. Default values are populated automatically upon type selection.
            </p>
        </section>
    </div>
</div>
