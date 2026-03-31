<?php

namespace Tests\Unit;

use App\Livewire\ServerTerminalConsole;
use App\Models\Server;
use App\Models\ServerTerminalPreset;
use App\Models\ServerTerminalRun;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ServerTerminalConsoleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_uses_connection_specific_default_commands(): void
    {
        $component = app(ServerTerminalConsole::class);

        $component->record = Server::make(['connection_type' => 'ssh_key']);
        $this->assertSame('whoami', $component->defaultCommand());

        $component->record = Server::make(['connection_type' => 'local']);
        $this->assertSame('pwd', $component->defaultCommand());

        $component->record = Server::make(['connection_type' => 'cpanel']);
        $this->assertSame('ping', $component->defaultCommand());
    }

    public function test_it_exposes_connection_specific_quick_commands(): void
    {
        $component = app(ServerTerminalConsole::class);

        $component->record = Server::make(['connection_type' => 'cpanel']);
        $this->assertSame('ping', $component->quickCommands()[0]['command']);

        $component->record = Server::make(['connection_type' => 'local']);
        $this->assertSame('pwd', $component->quickCommands()[0]['command']);
    }

    public function test_it_exposes_history_filters_for_the_console(): void
    {
        $component = app(ServerTerminalConsole::class);
        $component->record = Server::make(['connection_type' => 'ssh_key']);

        $this->assertSame([
            'all' => 'All commands',
            'identity' => 'Identity',
            'system' => 'System',
            'files' => 'Files',
            'runtime' => 'Runtime',
            'cpanel' => 'cPanel',
            'other' => 'Other',
        ], $component->historyFilterOptions());
    }

    public function test_it_suggests_saved_presets_and_recent_commands(): void
    {
        $server = Server::query()->create([
            'name' => 'Console Server',
            'ip_address' => '203.0.113.90',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        ServerTerminalPreset::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'name' => 'Check disk',
            'command' => 'df -h',
            'description' => 'Inspect disk usage.',
        ]);

        ServerTerminalRun::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'command' => 'uptime',
            'status' => 'successful',
            'output' => 'up 5 days',
            'exit_code' => 0,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10)->addSeconds(2),
        ]);

        $component = app(ServerTerminalConsole::class);
        $component->record = $server;
        $component->command = '';

        $suggestions = $component->autocompleteSuggestions();

        $this->assertNotEmpty($suggestions);
        $this->assertTrue(collect($suggestions)->contains(fn (array $suggestion): bool => $suggestion['command'] === 'df -h'));
        $this->assertTrue(collect($suggestions)->contains(fn (array $suggestion): bool => $suggestion['command'] === 'uptime'));
    }

    public function test_it_groups_and_filters_shell_presets_by_folder_and_tags(): void
    {
        $server = Server::query()->create([
            'name' => 'Grouped Preset Server',
            'ip_address' => '203.0.113.92',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'status' => 'online',
        ]);

        ServerTerminalPreset::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'group_name' => 'Deploy',
            'name' => 'Restart workers',
            'command' => 'php artisan queue:restart',
            'description' => 'Restart queued jobs.',
            'tags' => ['queue', 'runtime'],
        ]);

        ServerTerminalPreset::query()->create([
            'server_id' => $server->id,
            'user_id' => null,
            'group_name' => 'Diagnostics',
            'name' => 'Check disk',
            'command' => 'df -h',
            'description' => 'Inspect disk usage.',
            'tags' => ['system', 'files'],
        ]);

        $component = app(ServerTerminalConsole::class);
        $component->record = $server;

        $groups = $server->terminalPresetGroups(limit: 10, groupFilter: 'Deploy');
        $this->assertCount(1, $groups);
        $this->assertSame('Deploy', $groups[0]['group']);
        $this->assertSame('php artisan queue:restart', $groups[0]['presets'][0]['command']);

        $tagFiltered = $server->terminalPresetGroups(limit: 10, tagFilter: 'files');
        $this->assertCount(1, $tagFiltered);
        $this->assertSame('Diagnostics', $tagFiltered[0]['group']);

        $this->assertSame(['Deploy', 'Diagnostics'], $server->terminalPresetGroupOptions());
        $this->assertSame(['queue', 'runtime', 'system', 'files'], $server->terminalPresetTagOptions());
    }
}
