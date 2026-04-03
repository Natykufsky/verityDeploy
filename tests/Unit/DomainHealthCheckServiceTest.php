<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Domains\DomainHealthCheckService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainHealthCheckServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_marks_a_forbidden_domain_as_forbidden_with_a_helpful_hint(): void
    {
        $domain = $this->createDomain('verityapi.monaksoft.com.ng');

        Http::fake([
            'https://verityapi.monaksoft.com.ng/health' => Http::response('', 403, [
                'Server' => 'LiteSpeed',
            ]),
        ]);

        $preview = app(DomainHealthCheckService::class)->preview($domain);

        $this->assertTrue($preview['supported']);
        $this->assertSame('https://verityapi.monaksoft.com.ng/health', $preview['url']);
        $this->assertSame(403, $preview['status_code']);
        $this->assertSame('forbidden', $preview['status']);
        $this->assertSame('Forbidden', $preview['status_label']);
        $this->assertStringContainsString('403 Forbidden', $preview['message']);
        $this->assertStringContainsString('wrong document root', $preview['hint']);
        $this->assertSame('LiteSpeed', $preview['server_header']);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://verityapi.monaksoft.com.ng/health');
    }

    public function test_it_marks_a_healthy_domain_as_healthy(): void
    {
        $domain = $this->createDomain('verityapi.monaksoft.com.ng');

        Http::fake([
            'https://verityapi.monaksoft.com.ng/health' => Http::response('ok', 200, [
                'Server' => 'LiteSpeed',
            ]),
        ]);

        $preview = app(DomainHealthCheckService::class)->preview($domain);

        $this->assertTrue($preview['supported']);
        $this->assertSame(200, $preview['status_code']);
        $this->assertSame('healthy', $preview['status']);
        $this->assertSame('Healthy', $preview['status_label']);
        $this->assertStringContainsString('responded successfully', $preview['message']);
    }

    protected function createDomain(string $name): Domain
    {
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
            'name' => 'VerityAPI',
            'deploy_path' => '/home/monaksof/public_html/verityapi',
            'deploy_source' => 'local',
            'web_root' => 'public',
            'force_https' => true,
            'ssl_state' => 'valid',
            'health_check_endpoint' => '/health',
        ]);

        $domain = Domain::query()->create([
            'server_id' => $server->id,
            'site_id' => $site->id,
            'name' => $name,
            'type' => 'primary',
            'web_root' => 'public',
            'is_active' => true,
            'is_ssl_enabled' => true,
            'ssl_status' => 'issued',
        ]);

        $site->forceFill([
            'primary_domain_id' => $domain->id,
        ])->save();

        return $domain;
    }
}
