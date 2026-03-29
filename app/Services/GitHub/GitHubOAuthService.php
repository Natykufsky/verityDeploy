<?php

namespace App\Services\GitHub;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GitHubOAuthService
{
    public function authorizationUrl(): string
    {
        $settings = app(AppSettings::class);
        $clientId = $settings->githubOAuthClientId();
        $clientSecret = $settings->githubOAuthClientSecret();

        if (blank($clientId) || blank($clientSecret)) {
            throw new RuntimeException('GitHub OAuth is not configured yet. Add the client ID and secret in App Settings.');
        }

        $state = Str::random(40);
        session([
            'github_oauth_state' => $state,
        ]);

        return 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('github.oauth.callback'),
            'scope' => 'repo admin:repo_hook',
            'state' => $state,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCode(string $code, string $state): array
    {
        $settings = app(AppSettings::class);

        if ($state !== session('github_oauth_state')) {
            throw new RuntimeException('The GitHub OAuth state did not match. Please try connecting again.');
        }

        if (blank($settings->githubOAuthClientId()) || blank($settings->githubOAuthClientSecret())) {
            throw new RuntimeException('GitHub OAuth is not configured yet. Add the client ID and secret in App Settings.');
        }

        $response = Http::acceptJson()
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => config('app.name', 'verityDeploy'),
            ])
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $settings->githubOAuthClientId(),
                'client_secret' => $settings->githubOAuthClientSecret(),
                'code' => $code,
                'redirect_uri' => route('github.oauth.callback'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->messageFromResponse($response->body()));
        }

        $payload = $response->json();

        if (! is_array($payload) || blank(data_get($payload, 'access_token'))) {
            throw new RuntimeException('GitHub did not return an access token.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeToken(array $payload): void
    {
        app(AppSettings::class)->record()->update([
            'github_oauth_access_token' => data_get($payload, 'access_token'),
            'github_oauth_connected_at' => now(),
            'github_oauth_last_error' => null,
        ]);
    }

    public function disconnect(): void
    {
        app(AppSettings::class)->record()->update([
            'github_oauth_access_token' => null,
            'github_oauth_connected_at' => null,
            'github_oauth_last_error' => null,
        ]);
    }

    protected function messageFromResponse(string $body): string
    {
        parse_str($body, $parsed);

        return (string) ($parsed['error_description'] ?? $parsed['error'] ?? 'Unable to complete the GitHub OAuth exchange.');
    }
}
