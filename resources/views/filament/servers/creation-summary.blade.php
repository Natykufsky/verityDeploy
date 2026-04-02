<div class="space-y-6">
    <div class="p-6 bg-warning-50 dark:bg-warning-900/10 border border-warning-200 dark:border-warning-800 rounded-xl">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-warning-500 rounded-lg text-white">
                <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Security Handshake</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Please ensure the SSH key from the previous step is authorized on this server before clicking Create.</p>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Server Identity</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white" x-text="$wire.get('data.name') || 'Unnamed Server'"></p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">IP Address</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white font-mono" x-text="$wire.get('data.ip_address')"></p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">SSH User & Port</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white">
                <span x-text="$wire.get('data.ssh_user')"></span>:<span x-text="$wire.get('data.ssh_port')"></span>
            </p>
        </div>

        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Auth Method</span>
            <p class="mt-1 font-medium text-gray-900 dark:text-white capitalize" x-text="$wire.get('data.connection_type').replace('_', ' ')"></p>
        </div>
    </div>

    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700 rounded-lg flex items-start gap-3">
        <x-filament::icon icon="heroicon-m-information-circle" class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" />
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Clicking <strong>Create</strong> will add this server to your infrastructure. We will immediately attempt a pre-flight connection test to verify the SSH handshake.
        </p>
    </div>
</div>
