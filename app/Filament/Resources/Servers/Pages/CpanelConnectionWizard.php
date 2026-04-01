<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\Schemas\CpanelConnectionWizardInfolist;
use App\Services\Cpanel\CpanelWizardRunner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class CpanelConnectionWizard extends ViewServer
{
    public array $wizardLog = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->connection_type === 'cpanel', 404);
    }

    public function infolist(Schema $schema): Schema
    {
        return CpanelConnectionWizardInfolist::configure($schema);
    }

    public function getTitle(): string|Htmlable
    {
        return sprintf('%s - cPanel connection wizard', $this->record->name);
    }

    protected function getHeaderActions(): array
    {
        $actions = array_values(array_filter(
            parent::getHeaderActions(),
            fn (Action $action): bool => $action->getName() !== 'openCpanelConnectionWizard',
        ));

        array_unshift($actions, Action::make('runCpanelWizard')
            ->label('Run cPanel wizard')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Run the cPanel connection wizard?')
            ->modalDescription('This discovers the SSH port, validates the cPanel API, and runs the server provisioning preflight in one pass.')
            ->modalSubmitActionLabel('Run wizard')
            ->action(fn () => $this->runWizard()));

        array_splice($actions, 1, 0, [
            Action::make('backToServer')
                ->label('Back to server')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('view', [
                    'record' => $this->record,
                ])),
        ]);

        return $actions;
    }

    protected function runWizard(): void
    {
        $this->wizardLog = [];

        try {
            $this->wizardLog = app(CpanelWizardRunner::class)->runServerChecks($this->record->fresh());

            Notification::make()
                ->title('cPanel wizard complete')
                ->body('SSH port discovery, cPanel API validation, and server provisioning all completed successfully.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            $latestRun = $this->record->cpanelWizardRuns()
                ->latest('started_at')
                ->first();

            $this->record->update([
                'status' => 'error',
            ]);

            $this->wizardLog[] = [
                'step' => 'Wizard failed',
                'status' => 'failed',
                'message' => $throwable->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ];

            if (filled($latestRun?->recovery_hint)) {
                $this->wizardLog[] = [
                    'step' => 'Recovery guidance',
                    'status' => 'failed',
                    'message' => $latestRun->recovery_hint,
                    'recovery_hint' => $latestRun->recovery_hint,
                    'timestamp' => now()->toDateTimeString(),
                ];
            }

            Notification::make()
                ->title('cPanel wizard failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }
}
