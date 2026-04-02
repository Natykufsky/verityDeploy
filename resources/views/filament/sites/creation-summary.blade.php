<div class="space-y-6">
    <div class="p-6 bg-primary-50 dark:bg-primary-900/10 border border-primary-200 dark:border-primary-800 rounded-xl">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-primary-500 rounded-lg text-white">
                <x-filament::icon icon="heroicon-o-rocket-launch" class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Almost Ready!</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Review your site configuration before we begin the provisioning process.</p>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Application</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white" x-text="$wire.get('data.name') || 'Unnamed Site'"></p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Target Server</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white">Selected Backend Node</p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Deploy Path</span>
            <p class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-300 break-all" x-text="$wire.get('data.deploy_path')"></p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Source</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white capitalize" x-text="$wire.get('data.deploy_source')"></p>
        </div>
    </div>

    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg flex items-start gap-3">
        <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" />
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Once you click <strong>Create</strong>, we will initialize the directory structure and prepare the workspace on the target server. You will be able to trigger the first live deployment immediately after.
        </p>
    </div>
</div>
