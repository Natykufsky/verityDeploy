<?php

namespace Tests\Unit;

use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Widgets\SiteBackupOverviewCard;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteBackup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SiteBackupOverviewCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_surfaces_backup_counts_and_latest_copy(): void
    {
        $server = Server::query()->create([
            'name' => 'Backup Widget Server',
            'ip_address' => '203.0.113.201',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'provider_type' => 'digitalocean',
            'provider_reference' => 'droplet-201',
            'provider_region' => 'fra1',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'backup-widget-site',
            'deploy_path' => '/var/www/backup-widget-site',
            'current_release_path' => '/var/www/backup-widget-site/releases/20260329000000-1',
            'deploy_source' => 'git',
            'repository_url' => 'https://github.com/acme/backup-widget-site.git',
        ]);

        $backup = SiteBackup::query()->create([
            'site_id' => $site->id,
            'operation' => 'backup',
            'status' => 'successful',
            'label' => 'Latest backup',
            'source_release_path' => $site->current_release_path,
            'snapshot_path' => '/var/www/backup-widget-site/backups/20260329000000-1',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
        ]);

        SiteBackup::query()->create([
            'site_id' => $site->id,
            'operation' => 'restore',
            'status' => 'successful',
            'source_backup_id' => $backup->id,
            'restored_release_path' => '/var/www/backup-widget-site/releases/20260329001000-2',
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);

        $widget = new SiteBackupOverviewCard;
        $latestBackup = SiteBackup::query()
            ->where('operation', 'backup')
            ->latest('started_at')
            ->first();

        $this->assertSame(SiteResource::getUrl('view', ['record' => $site]), $widget->openLatestRun()->getTargetUrl());
        $this->assertSame('backup-widget-site', $this->invokeProtected($widget, 'latestBackupLabel', [$latestBackup]));
        $this->assertSame('emerald', $this->invokeProtected($widget, 'latestBackupTone', [$latestBackup]));
        $this->assertSame('abc123', substr((string) $this->invokeProtected($widget, 'latestBackupSummary', [$latestBackup]), -6));
        $this->assertGreaterThanOrEqual(1, $this->invokeProtected($widget, 'getViewData', [])['totalBackups']);
    }

    protected function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
