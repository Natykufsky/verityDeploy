<div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
    <header class="border-b border-gray-200 dark:border-gray-800 pb-4">
        <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">App Management Guide</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Master how to deploy and configure your applications in VerityDeploy.</p>
    </header>

    <div class="grid gap-8">
        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-rocket-launch class="w-5 h-5 text-primary-600" />
                Deployment Sources
            </h3>
            <ul class="space-y-4 text-sm">
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">Git Repository</strong>
                    <p class="text-gray-600 dark:text-gray-400">VerityDeploy SSHs into your server and pulls code directly from Git. Best for professional CI/CD workflows and automated updates.</p>
                </li>
                <li class="pl-4 border-l-2 border-gray-100 dark:border-gray-800">
                    <strong class="block text-gray-900 dark:text-gray-100">Local Machine</strong>
                    <p class="text-gray-600 dark:text-gray-400">The dashboard server packages your local codebase (from an absolute path) and uploads the archive to the target server. Best for quick prototyping or legacy systems.</p>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-variable class="w-5 h-5 text-primary-600" />
                Environment & Persistence
            </h3>
            <div class="grid sm:grid-cols-2 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-gray-800">
                    <span class="font-bold uppercase tracking-wider text-primary-600">Runtime Config</span>
                    <p class="mt-2 leading-relaxed text-gray-600 dark:text-gray-400">Use <strong>Individual Variables</strong> for standard key-value pairs, or the <strong>Full .env Override</strong> for complex configurations including comments and custom formats.</p>
                </div>
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-gray-800">
                    <span class="font-bold uppercase tracking-wider text-primary-600">Shared Files</span>
                    <p class="mt-2 leading-relaxed text-gray-600 dark:text-gray-400">Paths listed here are symbolic links that persist across releases (e.g. <code>storage/logs</code>). This ensures your application data isn't lost during deployment.</p>
                </div>
            </div>
        </section>

        <section class="space-y-3 text-sm">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <x-heroicon-o-magnifying-glass-circle class="w-5 h-5 text-primary-600" />
                Architecture Preview
            </h3>
            <p class="text-gray-600 dark:text-gray-400">The <strong>Architecture</strong> tab provides a real-time visualization of your Nginx Vhost configuration and DNS records before you hit deploy. Always verify your <strong>Absolute Deploy Path</strong> and <strong>Web Root</strong> here first.</p>
        </section>
    </div>
</div>
