<?php

namespace App\Livewire;

use App\Jobs\ExecuteServerTerminalCommand;
use App\Models\Server;
use App\Models\ServerTerminalPreset;
use App\Models\ServerTerminalRun;
use App\Models\ServerTerminalSession;
use App\Services\Terminal\TerminalSessionManager;
use App\Services\Terminal\TerminalTransport;
use Carbon\CarbonInterval;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ServerTerminalConsole extends Component
{
    public Server $record;

    public ?int $terminalSessionId = null;

    public string $command = 'whoami';

    public string $historyFilter = 'all';

    public string $presetName = '';

    public string $presetGroup = '';

    public string $presetTags = '';

    public string $presetDescription = '';

    public string $presetSearch = '';

    public string $presetGroupFilter = '';

    public string $presetTagFilter = '';

    public ?int $editingPresetId = null;

    public function mount(Server $record): void
    {
        $this->record = $record;
        $this->ensureTerminalSession();
        $this->command = $this->defaultCommand();
    }

    public function runCommand(): void
    {
        $command = trim($this->command);

        if ($command === '') {
            Notification::make()
                ->title('Enter a command first')
                ->body('Type a terminal command or use one of the quick commands below.')
                ->warning()
                ->send();

            return;
        }

        $run = ServerTerminalRun::query()->create([
            'server_id' => $this->record->id,
            'server_terminal_session_id' => $this->ensureTerminalSession()->id,
            'user_id' => Auth::id(),
            'command' => $command,
            'status' => 'queued',
            'started_at' => now(),
        ]);

        ExecuteServerTerminalCommand::dispatch($run->id);

        Notification::make()
            ->title('Command queued')
            ->body('The terminal command is running in the background. The console will update as output arrives.')
            ->success()
            ->send();

        $this->command = $this->defaultCommand();
    }

    public function runCommandFromTerminal(string $command): void
    {
        $this->command = $command;
        $this->runCommand();
    }

    public function useCommand(string $command): void
    {
        $this->command = $command;
    }

    public function loadPreset(int $presetId): void
    {
        $preset = $this->record->terminalPresets()->findOrFail($presetId);

        $this->editingPresetId = $preset->id;
        $this->presetName = $preset->name;
        $this->presetGroup = (string) $preset->group_name;
        $this->presetDescription = (string) $preset->description;
        $this->presetTags = collect($preset->tags ?? [])->implode(', ');
        $this->command = $preset->command;
    }

    public function savePreset(): void
    {
        $command = trim($this->command);
        $name = trim($this->presetName);
        $group = trim($this->presetGroup);
        $tags = collect(explode(',', $this->presetTags))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();

        if ($name === '' || $command === '') {
            Notification::make()
                ->title('Preset needs a name and command')
                ->body('Choose a label and make sure the command is not empty.')
                ->warning()
                ->send();

            return;
        }

        $preset = $this->editingPresetId
            ? $this->record->terminalPresets()->find($this->editingPresetId)
            : null;

        if ($preset) {
            $preset->update([
                'name' => $name,
                'command' => $command,
                'group_name' => filled($group) ? $group : null,
                'description' => filled($this->presetDescription) ? $this->presetDescription : null,
                'tags' => filled($tags) ? $tags : null,
            ]);
        } else {
            $preset = $this->record->terminalPresets()->create([
                'user_id' => Auth::id(),
                'name' => $name,
                'command' => $command,
                'group_name' => filled($group) ? $group : null,
                'description' => filled($this->presetDescription) ? $this->presetDescription : null,
                'tags' => filled($tags) ? $tags : null,
            ]);
        }

        $this->editingPresetId = $preset->id;

        Notification::make()
            ->title('Preset saved')
            ->body('You can launch this shell snippet again from the presets sidebar or the autocomplete list.')
            ->success()
            ->send();
    }

    public function deletePreset(int $presetId): void
    {
        $preset = $this->record->terminalPresets()->findOrFail($presetId);
        $preset->delete();

        if ($this->editingPresetId === $presetId) {
            $this->resetPresetForm();
        }

        Notification::make()
            ->title('Preset removed')
            ->body('The shell snippet was deleted from this server.')
            ->success()
            ->send();
    }

    public function executeQuickCommand(string $command): void
    {
        $this->command = $command;
        $this->runCommand();
    }

    public function resetPresetFilters(): void
    {
        $this->presetSearch = '';
        $this->presetGroupFilter = '';
        $this->presetTagFilter = '';
    }

    public function resetTerminalWorkspace(): void
    {
        $this->historyFilter = 'all';
        $this->presetSearch = '';
        $this->presetGroupFilter = '';
        $this->presetTagFilter = '';
        $this->resetPresetForm();

        $this->dispatch('verity-reset-server-terminal-workspace');
    }

    public function ensureTerminalSession(?TerminalTransport $transport = null): ServerTerminalSession
    {
        $transport ??= app(TerminalTransport::class);
        $session = $transport->open($this->record, Auth::id(), [
            'ui' => 'server-terminal',
        ]);

        $this->terminalSessionId = $session->id;

        return $session;
    }

    /**
     * @return array<string, string>
     */
    public function historyFilterOptions(): array
    {
        return [
            'all' => 'All commands',
            'identity' => 'Identity',
            'system' => 'System',
            'files' => 'Files',
            'runtime' => 'Runtime',
            'cpanel' => 'cPanel',
            'other' => 'Other',
        ];
    }

    /**
     * @return array<int, array{label: string, command: string, description: string}>
     */
    public function quickCommands(): array
    {
        return match ($this->record->connection_type) {
            'cpanel' => [
                [
                    'label' => 'api ping',
                    'command' => 'ping',
                    'description' => 'check the cpanel api path and token.',
                ],
                [
                    'label' => 'who am i',
                    'command' => 'whoami',
                    'description' => 'confirm the account username returned by cpanel.',
                ],
            ],
            'local' => [
                [
                    'label' => 'pwd',
                    'command' => 'pwd',
                    'description' => 'show the dashboard server working directory.',
                ],
                [
                    'label' => 'php -v',
                    'command' => 'php -v',
                    'description' => 'check the local php version.',
                ],
                [
                    'label' => 'composer -v',
                    'command' => 'composer -v',
                    'description' => 'confirm composer is available locally.',
                ],
            ],
            default => [
                [
                    'label' => 'whoami',
                    'command' => 'whoami',
                    'description' => 'confirm the ssh user on the remote server.',
                ],
                [
                    'label' => 'uptime',
                    'command' => 'uptime',
                    'description' => 'check server load and uptime.',
                ],
                [
                    'label' => 'df -h',
                    'command' => 'df -h',
                    'description' => 'inspect disk usage.',
                ],
            ],
        };
    }

    /**
     * @return array<int, array{id: int, name: string, command: string, description: string|null}>
     */
    public function presets(): array
    {
        return $this->record->terminalPresets()
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(fn (ServerTerminalPreset $preset): array => [
                'id' => $preset->id,
                'name' => $preset->name,
                'command' => $preset->command,
                'description' => $preset->description,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, command: string, description: string, source: string}>
     */
    public function autocompleteSuggestions(): array
    {
        $needle = strtolower(trim($this->command));
        $suggestions = collect($this->record->terminalAutocompleteSuggestions());

        if ($needle === '') {
            return $suggestions->take(8)->all();
        }

        return $suggestions
            ->filter(function (array $item) use ($needle): bool {
                $haystack = strtolower($item['command'].' '.$item['label'].' '.$item['description']);

                return str_contains($haystack, $needle);
            })
            ->take(8)
            ->values()
            ->all();
    }

    public function defaultCommand(): string
    {
        return match ($this->record->connection_type) {
            'cpanel' => 'ping',
            'local' => 'pwd',
            default => 'whoami',
        };
    }

    public function resetPresetForm(): void
    {
        $this->editingPresetId = null;
        $this->presetName = '';
        $this->presetGroup = '';
        $this->presetDescription = '';
        $this->presetTags = '';
        $this->command = $this->defaultCommand();
    }

    public function render(): View
    {
        $server = $this->record->fresh([
            'terminalRuns' => fn ($query) => $query->latest('started_at')->latest()->limit(8),
            'terminalPresets' => fn ($query) => $query->latest('updated_at')->limit(12),
            'terminalSessions' => fn ($query) => $query->latest('started_at')->limit(6),
        ]) ?? $this->record;

        $runs = $server->terminalRuns
            ->filter(function (ServerTerminalRun $run): bool {
                if ($this->historyFilter === 'all') {
                    return true;
                }

                return $this->classifyCommand($run->command) === $this->historyFilter;
            })
            ->values();

        return view('livewire.server-terminal-console', [
            'server' => $server,
            'runs' => $runs->map(fn (ServerTerminalRun $run): array => [
                'id' => $run->id,
                'started_at' => $run->started_at,
                'command' => $run->command,
                'status' => $run->status,
                'exit_code' => $run->exit_code,
                'error_message' => $run->error_message,
                'output' => $run->output,
                'duration_label' => $this->durationLabel($run),
            ])->all(),
            'runsCount' => $runs->count(),
            'presetGroups' => $server->terminalPresetGroups(
                limit: 24,
                search: $this->presetSearch ?: null,
                groupFilter: $this->presetGroupFilter ?: null,
                tagFilter: $this->presetTagFilter ?: null,
            ),
            'presetGroupOptions' => $server->terminalPresetGroupOptions(),
            'presetTagOptions' => $server->terminalPresetTagOptions(),
            'historyFilter' => $this->historyFilter,
            'historyFilterOptions' => $this->historyFilterOptions(),
            'quickCommands' => $this->quickCommands(),
            'autocompleteSuggestions' => $this->autocompleteSuggestions(),
            'terminalPrompt' => $server->terminal_prompt,
            'terminalSession' => app(TerminalSessionManager::class)->latestOpenForServer($server),
            'terminalSessionId' => $this->terminalSessionId,
        ]);
    }

    protected function classifyCommand(string $command): string
    {
        $normalized = strtolower(trim($command));

        return match (true) {
            in_array($normalized, ['whoami', 'id', 'hostname', 'uname -a'], true) => 'identity',
            in_array($normalized, ['uptime', 'free -m', 'free -h', 'top', 'htop'], true) => 'system',
            str_starts_with($normalized, 'php ') || str_starts_with($normalized, 'composer ') => 'runtime',
            str_starts_with($normalized, 'df ') || str_starts_with($normalized, 'ls ') || str_starts_with($normalized, 'pwd') || str_contains($normalized, 'mkdir ') => 'files',
            $this->record->connection_type === 'cpanel' && in_array($normalized, ['ping', 'api ping', 'cpanel ping'], true) => 'cpanel',
            default => 'other',
        };
    }

    protected function durationLabel(ServerTerminalRun $run): string
    {
        if ($run->status === 'running') {
            return 'running';
        }

        if (! $run->started_at || ! $run->finished_at) {
            return 'pending';
        }

        return CarbonInterval::seconds(max(1, (int) $run->started_at->diffInSeconds($run->finished_at)))
            ->cascade()
            ->forHumans(short: true);
    }
}
