<div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
    <header class="border-b border-gray-200 dark:border-gray-800 pb-4">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Server Management Guide</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Everything you need to know about connecting and managing servers in VerityDeploy.</p>
    </header>

    <div class="grid gap-8">
        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-signal class="w-5 h-5 text-primary-600" />
                Connection Types
            </h3>
            <ul class="space-y-4 text-sm">
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">SSH Key / Password</strong>
                    <p class="text-gray-600 dark:text-gray-400">Standard VPS connection using either an RSA/ED25519 key or a root password. Used for manual infrastructure management.</p>
                </li>
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">cPanel API</strong>
                    <p class="text-gray-600 dark:text-gray-400">Connect using a cPanel API Token. This mode enables automatic domain syncing, SSL issuing, and email management via the UAPI.</p>
                </li>
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">Localhost</strong>
                    <p class="text-gray-600 dark:text-gray-400">Used if the dashboard is running on the same machine it's managing. Skips network latency and external SSH requirements.</p>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-primary-600" />
                Key Operations
            </h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-gray-800">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary-600">Discover SSH Port</span>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">For cPanel servers, use the "Discover" button to automatically fetch the account's specific SSH port from the API.</p>
                </div>
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-gray-800">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary-600">Service Capabilities</span>
                    <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">Manually toggle what VerityDeploy can do on this machine (DNS, Vhosts, SSL) based on your hosting plan.</p>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2 text-warning-600">
                <x-heroicon-o-shield-check class="w-5 h-5" />
                Connectivity Issues (Error 28)
            </h3>
            <div class="p-4 rounded-xl bg-warning-50 dark:bg-warning-950/20 border border-warning-200 dark:border-warning-800/40">
                <p class="text-xs leading-relaxed text-warning-800 dark:text-warning-300">
                    If you get a <strong>Connection Timeout</strong>, please whitelist the Dashboard server's IP in your server's firewall (CSF or cPHulk).
                </p>
            </div>
        </section>
    </div>
</div>
