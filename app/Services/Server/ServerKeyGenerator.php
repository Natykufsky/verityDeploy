<?php

namespace App\Services\Server;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Services\Security\SshKeyService;

class ServerKeyGenerator
{
    /**
     * @return array{private_key: string, public_key: string}
     */
    public function generate(Server $server): array
    {
        $pair = app(SshKeyService::class)->generateKeyPair('ed25519');
        $profile = $server->sshCredentialProfile
            ?: CredentialProfile::query()->create([
                'name' => sprintf('SSH key for %s', $server->name),
                'type' => 'ssh',
                'description' => 'Automatically generated SSH credential profile for server access.',
                'settings' => [],
                'is_default' => false,
                'is_active' => true,
            ]);

        $profile->update([
            'settings' => array_merge((array) $profile->settings, [
                'private_key' => $pair['private_key'],
                'public_key' => $pair['public_key'],
            ]),
        ]);

        $server->update([
            'connection_type' => 'ssh_key',
            'ssh_credential_profile_id' => $profile->id,
        ]);

        return [
            'private_key' => $pair['private_key'],
            'public_key' => $pair['public_key'],
        ];
    }
}
