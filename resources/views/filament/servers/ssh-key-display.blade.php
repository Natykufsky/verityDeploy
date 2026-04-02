<div class="p-6 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">SSH Public Key</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Authorize this key on your servers to allow VerityDeploy to manage them.</p>
        </div>
        <x-filament::button
            color="gray"
            icon="heroicon-m-clipboard"
            x-on:click="
                window.navigator.clipboard.writeText($el.parentElement.nextElementSibling.querySelector('code').innerText);
                new FilamentNotification()
                    .title('Key copied to clipboard')
                    .success()
                    .send();
            "
        >
            Copy Key
        </x-filament::button>
    </div>

    <div class="relative group mt-2">
        <pre class="overflow-x-auto p-4 bg-white dark:bg-black rounded-lg border border-gray-100 dark:border-gray-800"><code class="text-xs font-mono text-gray-800 dark:text-gray-200 break-all whitespace-pre-wrap">{{ @file_get_contents(base_path('.ssh/id_rsa.pub')) ?: 'No key found. Use the generate button below.' }}</code></pre>

        @if(!@file_exists(base_path('.ssh/id_rsa.pub')))
            <div class="absolute inset-0 flex items-center justify-center bg-white/50 backdrop-blur-sm dark:bg-black/50 rounded-lg">
                <p class="text-sm font-medium text-red-500">No SSH Key detected on Dashboard server.</p>
            </div>
        @endif
    </div>

    <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-semibold mb-2 flex items-center gap-2">
            <x-filament::icon icon="heroicon-m-information-circle" class="w-4 h-4 text-primary-500" />
            Quick Setup Guide
        </h4>
        <ul class="text-xs space-y-2 text-gray-600 dark:text-gray-400 list-decimal list-inside">
            <li>Copy the public key above.</li>
            <li>Go to <strong>cPanel > SSH Access > Manage SSH Keys</strong>.</li>
            <li>Click <strong>Import Key</strong> and paste the content in the "Public Key" field.</li>
            <li>After importing, click <strong>Manage</strong> > <strong>Authorize</strong> on the key list.</li>
        </ul>
    </div>
</div>
