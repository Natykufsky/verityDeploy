<div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
    <header class="border-b border-gray-200 dark:border-gray-800 pb-4">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Domain & SSL Guide</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your server domains, virtual host routing, and SSL certificates.</p>
    </header>

    <div class="grid gap-8">
        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-globe-alt class="w-5 h-5 text-primary-600" />
                Domain Types
            </h3>
            <ul class="space-y-4 text-sm">
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">Primary Domain</strong>
                    <p class="text-gray-600 dark:text-gray-400">The main domain for a site or server. Usually matches the system hostname or main web application URL.</p>
                </li>
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">Addon / Alias</strong>
                    <p class="text-gray-600 dark:text-gray-400">On cPanel servers, an <strong>Addon</strong> has its own web root, while an <strong>Alias</strong> (Parked) mirrors the primary domain's content.</p>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-lock-closed class="w-5 h-5 text-primary-600" />
                SSL & Security
            </h3>
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-gray-800">
                <ul class="space-y-2 text-xs">
                    <li><strong class="text-primary-600">AutoSSL:</strong> For cPanel servers, select "Manage SSL" to let the server handle certificate issuance automatically.</li>
                    <li><strong class="text-primary-600">Manual Certs:</strong> Paste your own CRT, KEY, and Bundle data in the "Manual Certificate Data" section for custom setups.</li>
                    <li><strong class="text-primary-600">HTTPS Redirect:</strong> Enable "Force HTTPS" to automatically write the <code>.htaccess</code> or Nginx redirect rules.</li>
                </ul>
            </div>
        </section>

        <section class="space-y-3 text-sm">
            <h3 class="text-lg font-semibold flex items-center gap-2 text-info-600">
                <x-heroicon-o-arrow-path class="w-5 h-5" />
                Importing Domains
            </h3>
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                Use the <strong>"Import from server"</strong> button on the main domain list to automatically sync your existing cPanel domains. This will fetch addon domains, subdomains, and their web root configurations into VerityDeploy.
            </p>
        </section>
    </div>
</div>
