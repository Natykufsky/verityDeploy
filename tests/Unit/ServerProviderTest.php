<?php

namespace Tests\Unit;

use App\Models\Server;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServerProviderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_exposes_provider_labels_and_summary_text(): void
    {
        $server = Server::query()->create([
            'name' => 'Provider Server',
            'ip_address' => '203.0.113.200',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'provider_type' => 'digitalocean',
            'provider_reference' => 'droplet-12345',
            'provider_region' => 'fra1',
            'provider_metadata' => [
                'plan' => 's-2vcpu-4gb',
                'tags' => 'production,web',
            ],
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $this->assertSame('DigitalOcean', $server->provider_label);
        $this->assertStringContainsString('droplet-12345', $server->provider_summary);
        $this->assertStringContainsString('fra1', $server->provider_summary);
        $this->assertSame('digitalocean', $server->provider_state['type']);
        $this->assertSame('Manual / Custom', Server::providerOptions()['manual']);
    }
}
