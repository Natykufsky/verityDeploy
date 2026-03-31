@php
    $connectionType = $get('connection_type');
@endphp

<div class="rounded-2xl border border-slate-200 bg-slate-50/90 p-4 text-sm text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200">
    <div class="mb-2 flex items-center gap-2">
        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-sky-500"></span>
        <p class="font-semibold text-slate-900 dark:text-white">Connection mode help</p>
    </div>

    @if ($connectionType === 'cpanel')
        <p class="leading-6">
            Use your cPanel account username in <span class="font-mono">SSH user</span>, store the API token in the cPanel tab, and keep the deploy path under the account home directory.
        </p>
    @elseif ($connectionType === 'local')
        <p class="leading-6">
            The dashboard server will run commands locally, so this is best for self-hosted installs or CI-style build boxes.
        </p>
    @elseif ($connectionType === 'password')
        <p class="leading-6">
            Password mode is supported for legacy SSH servers, but an SSH key is usually safer and easier to rotate.
        </p>
    @else
        <p class="leading-6">
            SSH key mode is the recommended default for Linux servers. Store the private key encrypted and keep sudo access separate when possible.
        </p>
    @endif

    <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
        <p class="font-medium text-slate-900 dark:text-slate-100">Quick checklist</p>
        <ul class="mt-2 list-disc space-y-1 pl-4">
            <li>Choose the connection type that matches the server platform.</li>
            <li>Use the Provider tab to record the cloud vendor, region, and any infrastructure notes.</li>
            <li>Keep sensitive values encrypted in the server record.</li>
            <li>Use Export PuTTY key if you need a Windows-friendly .ppk file for SSH tooling.</li>
            <li>Use Discover port and Test API on cPanel servers after saving the record.</li>
            <li>Run a test connection after saving the server.</li>
        </ul>
    </div>
</div>
