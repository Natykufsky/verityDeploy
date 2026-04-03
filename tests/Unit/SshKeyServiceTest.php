<?php

namespace Tests\Unit;

use App\Models\CredentialProfile;
use App\Models\Server;
use App\Services\Security\SshKeyService;
use App\Services\Server\ServerKeyGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

class SshKeyServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_normalizes_private_keys_into_openssh_format(): void
    {
        $rawPrivateKey = RSA::createKey(2048)->toString('PKCS8');

        $normalized = app(SshKeyService::class)->normalizePrivateKey($rawPrivateKey);

        $this->assertNotNull($normalized);
        $this->assertStringStartsWith('-----BEGIN OPENSSH PRIVATE KEY-----', $normalized);
    }

    public function test_it_exports_deploy_private_keys_without_passphrases(): void
    {
        $passphrase = 'deploy-passphrase';
        $privateKey = RSA::createKey(2048)->withPassword($passphrase)->toString('OpenSSH');

        $deployKey = app(SshKeyService::class)->exportDeployPrivateKey($privateKey, $passphrase);

        $this->assertNotNull($deployKey);
        $this->assertStringStartsWith('-----BEGIN OPENSSH PRIVATE KEY-----', $deployKey);

        $loaded = PublicKeyLoader::load($deployKey, '');
        $this->assertSame(
            $loaded->getPublicKey()->toString('OpenSSH'),
            app(SshKeyService::class)->derivePublicKey($privateKey, $passphrase),
        );
    }

    public function test_server_key_generator_stores_openssh_private_keys(): void
    {
        $server = Server::query()->create([
            'name' => 'Key Test Server',
            'ip_address' => '203.0.113.250',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'connection_type' => 'cpanel',
            'status' => 'pending',
        ]);

        $result = app(ServerKeyGenerator::class)->generate($server);
        $profile = CredentialProfile::query()->findOrFail($server->fresh()->ssh_credential_profile_id);

        $this->assertStringStartsWith('-----BEGIN OPENSSH PRIVATE KEY-----', $result['private_key']);
        $this->assertStringStartsWith('ssh-ed25519 ', $result['public_key']);
        $this->assertSame($result['private_key'], $profile->settings['private_key']);
        $this->assertSame($result['public_key'], $profile->settings['public_key']);
        $this->assertSame('ssh_key', $server->fresh()->connection_type);
    }
}
