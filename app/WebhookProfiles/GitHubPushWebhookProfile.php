<?php

namespace App\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class GitHubPushWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        return $request->header('X-GitHub-Event') === 'push';
    }
}
