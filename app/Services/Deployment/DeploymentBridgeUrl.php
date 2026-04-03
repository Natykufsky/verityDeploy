<?php

namespace App\Services\Deployment;

use App\Models\Deployment;

class DeploymentBridgeUrl
{
    public function __construct(
        protected DeploymentBridgeAuth $auth,
    ) {}

    public function make(Deployment $deployment): array
    {
        $bridge = config('deployment.bridge', []);

        if (! ($bridge['enabled'] ?? false)) {
            return [
                'enabled' => false,
                'url' => null,
                'token' => null,
            ];
        }

        $token = $this->auth->token($deployment);

        return [
            'enabled' => true,
            'url' => sprintf(
                '%s://%s:%d?deployment_id=%d&token=%s',
                $bridge['scheme'] ?? 'ws',
                $bridge['host'] ?? '127.0.0.1',
                (int) ($bridge['port'] ?? 8789),
                $deployment->id,
                urlencode($token),
            ),
            'token' => $token,
        ];
    }
}
