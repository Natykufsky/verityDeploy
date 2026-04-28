<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Models\Site;
use App\Services\Files\SiteFileManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteFileManagerServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_browses_reads_and_saves_local_site_files(): void
    {
        $basePath = storage_path('framework/testing/'.Str::uuid());
        $releasePath = $basePath.'/current';

        File::ensureDirectoryExists($releasePath.'/config');
        File::put($releasePath.'/index.php', '<?php echo "verityDeploy";');
        File::put($releasePath.'/composer.json', json_encode(['name' => 'acme/site'], JSON_PRETTY_PRINT));
        File::put($releasePath.'/config/app.php', '<?php return [];');

        $server = Server::query()->create([
            'name' => 'File Manager Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'local',
            'provider_type' => 'local',
            'connection_type' => 'local',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'file-manager-site',
            'deploy_path' => $basePath,
            'current_release_path' => $releasePath,
            'deploy_source' => 'local',
        ]);

        $service = app(SiteFileManagerService::class);

        try {
            $browse = $service->browse($site->fresh(['server']));

            $this->assertSame('', $browse['relative_path']);
            $this->assertContains('composer.json', array_column($browse['items'], 'name'));
            $this->assertContains('config', array_column($browse['items'], 'name'));

            $file = $service->read($site->fresh(['server']), 'composer.json');
            $this->assertStringContainsString('acme\/site', $file['contents']);

            $saved = $service->save($site->fresh(['server']), 'composer.json', "{\n    \"name\": \"acme/veritydeploy\"\n}");
            $this->assertStringContainsString('acme/veritydeploy', $saved['contents']);
            $this->assertStringContainsString('acme/veritydeploy', File::get($releasePath.'/composer.json'));

            $nested = $service->browse($site->fresh(['server']), 'config');
            $this->assertSame('config', $nested['relative_path']);
            $this->assertContains('app.php', array_column($nested['items'], 'name'));
        } finally {
            File::deleteDirectory($basePath);
        }
    }

    public function test_it_never_allows_path_traversal_outside_the_release_root(): void
    {
        $basePath = storage_path('framework/testing/'.Str::uuid());
        $releasePath = $basePath.'/current';

        File::ensureDirectoryExists($releasePath);
        File::put($releasePath.'/index.php', '<?php echo "verityDeploy";');

        $server = Server::query()->create([
            'name' => 'Traversal Server',
            'ip_address' => '127.0.0.1',
            'ssh_port' => 22,
            'ssh_user' => 'local',
            'provider_type' => 'local',
            'connection_type' => 'local',
            'status' => 'online',
        ]);

        $site = Site::query()->create([
            'server_id' => $server->id,
            'name' => 'traversal-site',
            'deploy_path' => $basePath,
            'current_release_path' => $releasePath,
            'deploy_source' => 'local',
        ]);

        $service = app(SiteFileManagerService::class);

        try {
            $file = $service->read($site->fresh(['server']), '../index.php');

            $this->assertStringContainsString('verityDeploy', $file['contents']);
            $this->assertSame('index.php', $file['relative_path']);
        } finally {
            File::deleteDirectory($basePath);
        }
    }
}
