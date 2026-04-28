<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Services\Backups\SiteBackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteBackupServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_and_restores_a_local_release_backup(): void
    {
        $basePath = storage_path('framework/testing/'.Str::uuid());
        $releasePath = $basePath.'/releases/20260329000000-1';
        $restoreRoot = $basePath.'/restores';

        File::ensureDirectoryExists($releasePath);
        File::ensureDirectoryExists($restoreRoot);
        File::put($releasePath.'/index.php', '<?php echo "verityDeploy";');

        $user = User::query()->create([
            'name' => 'Backup User',
            'email' => 'backup@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Local Backup Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'local',
            'provider_type' => 'local',
            'connection_type' => 'local',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'team_id' => null,
            'name' => 'backup-site',
            'deploy_path' => $basePath,
            'current_release_path' => $releasePath,
            'deploy_source' => 'local',
            'local_source_path' => $basePath.'/source',
        ]);

        $service = app(SiteBackupService::class);

        try {
            $backup = $service->backup($site->fresh(['server']), $user, 'Demo backup');

            $this->assertSame('successful', $backup->status);
            $this->assertNotEmpty($backup->snapshot_path);
            $this->assertFileExists($backup->snapshot_path.'/index.php');
            $this->assertSame('healthy', $site->fresh()->backup_status);
            $this->assertNotEmpty($site->fresh()->backupOptions());

            $restore = $service->restore($backup->fresh(['site.server']), $user);
            $site = $site->fresh();

            $this->assertSame('successful', $restore->status);
            $this->assertNotEmpty($restore->restored_release_path);
            $this->assertSame($restore->restored_release_path, $site->current_release_path);
            $this->assertFileExists($restore->restored_release_path.'/index.php');
            $this->assertStringContainsString('restored', strtolower($restore->output ?? ''));
        } finally {
            File::deleteDirectory($basePath);
        }
    }

    public function test_it_prunes_old_backups_using_the_site_retention_count(): void
    {
        $basePath = storage_path('framework/testing/'.Str::uuid());
        $releasePath = $basePath.'/releases/20260329000000-1';

        File::ensureDirectoryExists($releasePath);
        File::put($releasePath.'/index.php', '<?php echo "verityDeploy";');

        $user = User::query()->create([
            'name' => 'Backup User',
            'email' => 'backup-retention@example.com',
            'password' => bcrypt('password'),
        ]);

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'Retention Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'local',
            'provider_type' => 'local',
            'connection_type' => 'local',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'team_id' => null,
            'name' => 'retention-site',
            'deploy_path' => $basePath,
            'current_release_path' => $releasePath,
            'deploy_source' => 'local',
            'local_source_path' => $basePath.'/source',
            'backup_enabled' => true,
            'backup_schedule' => 'daily',
            'backup_retention_count' => 2,
        ]);

        $service = app(SiteBackupService::class);

        try {
            $first = $service->backup($site->fresh(['server']), $user, 'Backup one');
            sleep(1);
            $second = $service->backup($site->fresh(['server']), $user, 'Backup two');
            sleep(1);
            $third = $service->backup($site->fresh(['server']), $user, 'Backup three');

            $this->assertSame('successful', $third->status);
            $this->assertSame(2, $site->fresh()->backups()->where('operation', 'backup')->count());
            $this->assertFileDoesNotExist($first->snapshot_path);
            $this->assertFileExists($second->snapshot_path);
            $this->assertFileExists($third->snapshot_path);
        } finally {
            File::deleteDirectory($basePath);
        }
    }
}
