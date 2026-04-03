<?php

namespace App\Services\Deployment;

use App\Models\Deployment;

class DeploymentBridgeAuth
{
    public function token(Deployment $deployment): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                $deployment->id,
                $deployment->site_id,
                $deployment->source,
                $deployment->created_at?->timestamp ?? 0,
                $deployment->release_path ?? '',
            ]),
            (string) config('app.key').'|deployment-stream',
        );
    }

    public function validate(Deployment $deployment, string $token): bool
    {
        return hash_equals($this->token($deployment), $token);
    }
}
