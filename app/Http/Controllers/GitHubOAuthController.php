<?php

namespace App\Http\Controllers;

use App\Services\GitHub\GitHubOAuthService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GitHubOAuthController
{
    public function redirect(GitHubOAuthService $service): RedirectResponse
    {
        return redirect()->away($service->authorizationUrl());
    }

    public function callback(Request $request, GitHubOAuthService $service): RedirectResponse|Response
    {
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if (blank($state) || blank($code)) {
            abort(400, 'GitHub OAuth did not return the required state or code.');
        }

        try {
            $payload = $service->exchangeCode($code, $state);
            $service->storeToken($payload);

            session()->forget('github_oauth_state');

            Notification::make()
                ->title('GitHub OAuth connected')
                ->body('GitHub webhook provisioning can now use the OAuth token.')
                ->success()
                ->send();

            return redirect('/admin/app-settings');
        } catch (Throwable $throwable) {
            app(\App\Services\AppSettings::class)->record()->update([
                'github_oauth_last_error' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->title('GitHub OAuth failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return redirect('/admin/app-settings');
        }
    }
}
