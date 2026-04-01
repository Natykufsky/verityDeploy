<?php

namespace App\Services\GitHub;

use App\Models\Site;
use App\Services\AppSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WebhookProvisioner
{
    public function provision(Site $site): array
    {
        $settings = app(AppSettings::class);
        $token = $site->githubCredentialProfile?->settings['token']
            ?? $site->githubCredentialProfile?->settings['api_token']
            ?? $settings->record()->github_oauth_access_token
            ?? env('GITHUB_API_TOKEN');

        if (blank($token)) {
            throw new RuntimeException('Missing GitHub API token. Attach a GitHub Credential Profile to the site, or set GITHUB_API_TOKEN in your environment.');
        }

        $repository = $this->parseRepository($site->repository_url);

        if (! $repository) {
            throw new RuntimeException('Repository URL must point to a GitHub repository.');
        }

        $webhookSecret = $site->effectiveWebhookSecret();

        if (blank($webhookSecret)) {
            throw new RuntimeException('Missing Webhook secret. Set a secret in the associated Webhook Credential Profile, or set a default in App Settings.');
        }

        $payload = [
            'name' => 'web',
            'active' => true,
            'events' => $settings->githubWebhookEvents(),
            'config' => [
                'url' => url($settings->githubWebhookPath()),
                'content_type' => 'json',
                'secret' => $webhookSecret,
                'insecure_ssl' => '0',
            ],
        ];

        $endpoint = "https://api.github.com/repos/{$repository['owner']}/{$repository['repo']}/hooks";

        $response = null;

        if (filled($site->github_webhook_id)) {
            $response = $this->request($token)->patch("{$endpoint}/{$site->github_webhook_id}", $payload);
        }

        if ((! $response) || (! $response->successful())) {
            $response = $this->request($token)->post($endpoint, $payload);
        }

        $data = $this->decodeResponse($response);
        $webhookId = data_get($data, 'id') ?? $site->github_webhook_id;

        $site->update([
            'github_webhook_id' => (string) $webhookId,
            'github_webhook_status' => 'provisioned',
            'github_webhook_synced_at' => now(),
            'github_webhook_last_error' => null,
        ]);

        return $data;
    }

    public function refreshStatus(Site $site): array
    {
        $settings = app(AppSettings::class);
        $token = $site->githubCredentialProfile?->settings['token']
            ?? $site->githubCredentialProfile?->settings['api_token']
            ?? $settings->record()->github_oauth_access_token
            ?? env('GITHUB_API_TOKEN');

        if (blank($token)) {
            throw new RuntimeException('Missing GitHub API token. Attach a GitHub Credential Profile to the site, or set GITHUB_API_TOKEN in your environment.');
        }

        $repository = $this->parseRepository($site->repository_url);

        if (! $repository) {
            throw new RuntimeException('Repository URL must point to a GitHub repository.');
        }

        if (blank($site->github_webhook_id)) {
            $site->update([
                'github_webhook_status' => 'unprovisioned',
                'github_webhook_last_error' => null,
                'github_webhook_synced_at' => now(),
            ]);

            return [
                'status' => 'unprovisioned',
            ];
        }

        $endpoint = "https://api.github.com/repos/{$repository['owner']}/{$repository['repo']}/hooks/{$site->github_webhook_id}";
        $response = $this->request($token)->get($endpoint);

        if ($response->successful()) {
            $site->update([
                'github_webhook_status' => 'provisioned',
                'github_webhook_synced_at' => now(),
                'github_webhook_last_error' => null,
            ]);

            return $response->json() ?? [];
        }

        if ($response->status() === 404) {
            $site->update([
                'github_webhook_status' => 'needs-sync',
                'github_webhook_synced_at' => now(),
                'github_webhook_last_error' => 'Remote GitHub webhook was not found.',
            ]);

            return [
                'status' => 'needs-sync',
            ];
        }

        $message = data_get($response->json(), 'message', 'Unable to refresh GitHub webhook status.');

        $site->update([
            'github_webhook_status' => 'failed',
            'github_webhook_synced_at' => now(),
            'github_webhook_last_error' => $message,
        ]);

        throw new RuntimeException($message);
    }

    public function remove(Site $site): void
    {
        $settings = app(AppSettings::class);
        $token = $site->githubCredentialProfile?->settings['token']
            ?? $site->githubCredentialProfile?->settings['api_token']
            ?? $settings->record()->github_oauth_access_token
            ?? env('GITHUB_API_TOKEN');

        if (blank($token)) {
            throw new RuntimeException('Missing GitHub API token. Attach a GitHub Credential Profile to the site, or set GITHUB_API_TOKEN in your environment.');
        }

        $repository = $this->parseRepository($site->repository_url);

        if (! $repository) {
            throw new RuntimeException('Repository URL must point to a GitHub repository.');
        }

        if (blank($site->github_webhook_id)) {
            $site->update([
                'github_webhook_status' => 'unprovisioned',
                'github_webhook_last_error' => null,
            ]);

            return;
        }

        $endpoint = "https://api.github.com/repos/{$repository['owner']}/{$repository['repo']}/hooks/{$site->github_webhook_id}";

        $response = $this->request($token)->delete($endpoint);

        if (! $response->successful() && $response->status() !== 404) {
            $message = data_get($response->json(), 'message', 'Unable to remove GitHub webhook.');

            throw new RuntimeException($message);
        }

        $site->update([
            'github_webhook_id' => null,
            'github_webhook_status' => 'unprovisioned',
            'github_webhook_synced_at' => now(),
            'github_webhook_last_error' => null,
        ]);
    }

    protected function request(string $token): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name', 'verityDeploy'),
            ]);
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    protected function parseRepository(?string $repositoryUrl): ?array
    {
        if (blank($repositoryUrl)) {
            return null;
        }

        $repositoryUrl = Str::of($repositoryUrl)->trim()->lower()->toString();
        $repositoryUrl = preg_replace('/\.git$/', '', $repositoryUrl) ?? $repositoryUrl;
        $repositoryUrl = rtrim($repositoryUrl, '/');

        if (preg_match('#(?:https://github\.com/|git@github\.com:)([^/]+)/([^/]+)$#', $repositoryUrl, $matches) !== 1) {
            return null;
        }

        return [
            'owner' => $matches[1],
            'repo' => $matches[2],
        ];
    }

    protected function decodeResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $message = data_get($response->json(), 'message', 'Unable to provision GitHub webhook.');

        throw new RuntimeException($message);
    }
}
