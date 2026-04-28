<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DomainSslManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class DomainSslManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_reports_ssl_and_renewal_state(): void
    {
        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'status' => 'online',
        ]);

        $domain = Domain::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_ssl_enabled' => true,
            'ssl_status' => 'issued',
            'ssl_expires_at' => now()->addDays(45),
            'ssl_certificate' => 'CERT',
            'ssl_key' => 'KEY',
            'ssl_chain' => 'CHAIN',
            'is_active' => true,
        ]);

        $preview = app(DomainSslManagementService::class)->preview($domain);

        $this->assertSame('issued', $preview['ssl_status']);
        $this->assertSame('healthy', $preview['renewal_status']);
        $this->assertSame('stored', $preview['certificate_present'] ? 'stored' : 'missing');
        $this->assertSame('stored', $preview['key_present'] ? 'stored' : 'missing');
        $this->assertSame('stored', $preview['chain_present'] ? 'stored' : 'missing');
    }

    public function test_scan_server_alerts_on_expiring_and_expired_domains(): void
    {
        $user = User::query()->create([
            'name' => 'SSL Watcher',
            'email' => 'ssl@example.com',
            'password' => bcrypt('password'),
            'alert_inbox_enabled' => true,
            'alert_minimum_level' => 'warning',
        ]);

        $server = Server::query()->create([
            'name' => 'Cpanel Server',
            'ip_address' => 'monaksoft.com',
            'ssh_port' => 22,
            'ssh_user' => 'monaksof',
            'connection_type' => 'cpanel',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'verityapi',
            'deploy_path' => '/home/monaksof/public_html/verityapi.monaksoft.com.ng',
        ]);

        $expiringDomain = Domain::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => 'expiring.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_ssl_enabled' => true,
            'ssl_status' => 'issued',
            'ssl_expires_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $expiredDomain = Domain::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => 'expired.monaksoft.com.ng',
            'type' => 'subdomain',
            'is_ssl_enabled' => true,
            'ssl_status' => 'issued',
            'ssl_expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $summary = app(DomainSslManagementService::class)->scanServer($server);

        $this->assertSame('1 SSL certificate is expiring within 30 days.', $summary[0]);
        $this->assertSame('1 SSL certificate is already expired.', $summary[1]);
        $this->assertSame('expired', $expiredDomain->fresh()->ssl_status);

        $notifications = DatabaseNotification::query()
            ->where('notifiable_id', $user->id)
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertContains('SSL expiring soon: expiring.monaksoft.com.ng', $notifications->pluck('data.title')->all());
        $this->assertContains('SSL expired: expired.monaksoft.com.ng', $notifications->pluck('data.title')->all());
    }
}
