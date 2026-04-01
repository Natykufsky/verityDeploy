<?php

namespace App\Livewire;

use App\Jobs\ExecuteSiteTerminalCommand;
use App\Models\Site;
use App\Models\SiteTerminalRun;
use Carbon\CarbonInterval;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SiteTerminalConsole extends Component
{
    public Site $record;

    public string $command = 'pwd';

    public function mount(Site $site): void
    {
        $this->record = $site;
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

        $run = SiteTerminalRun::query()->create([
            'site_id' => $this->record->id,
            'user_id' => auth()->id(),
            'command' => $command,
            'status' => 'queued',
            'started_at' => now(),
        ]);

        ExecuteSiteTerminalCommand::dispatch($run->id);

        Notification::make()
            ->title('Command queued')
            ->body('The site terminal command is running in the background. Output will appear as soon as it starts streaming.')
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

    public function resetTerminalWorkspace(): void
    {
        $this->command = $this->defaultCommand();
    }

    /**
     * @return array<int, array{label: string, command: string, description: string}>
     */
    public function quickCommands(): array
    {
        return [
            [
                'label' => 'pwd',
                'command' => 'pwd',
                'description' => 'confirm the terminal starts in the site folder.',
            ],
            [
                'label' => 'ls -la',
                'command' => 'ls -la',
                'description' => 'inspect the site files in the current release folder.',
            ],
            [
                'label' => 'git status',
                'command' => 'git status',
                'description' => 'check repository status when the site uses git deploys.',
            ],
            [
                'label' => 'php -v',
                'command' => 'php -v',
                'description' => 'inspect the php runtime available to the site.',
            ],
        ];
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
        return 'pwd';
    }

    public function render(): View
    {
        $site = $this->record->fresh([
            'terminalRuns' => fn ($query) => $query->latest('started_at')->latest()->limit(8),
        ]) ?? $this->record;

        return view('livewire.site-terminal-console', [
            'site' => $site,
            'runs' => $site->terminalRuns->map(fn (SiteTerminalRun $run): array => [
                'id' => $run->id,
                'started_at' => $run->started_at,
                'command' => $run->command,
                'status' => $run->status,
                'exit_code' => $run->exit_code,
                'error_message' => $run->error_message,
                'output' => $run->output,
                'duration_label' => $this->durationLabel($run),
            ])->all(),
            'runsCount' => $site->terminalRuns()->count(),
            'quickCommands' => $this->quickCommands(),
            'autocompleteSuggestions' => $this->autocompleteSuggestions(),
            'terminalPrompt' => $site->terminal_prompt,
            'sitePath' => $site->deploy_path,
        ]);
    }

    protected function durationLabel(SiteTerminalRun $run): string
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
