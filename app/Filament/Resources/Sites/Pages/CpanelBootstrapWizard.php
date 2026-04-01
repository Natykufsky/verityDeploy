<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\Schemas\CpanelBootstrapWizardInfolist;
use App\Services\Cpanel\CpanelWizardRunner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class CpanelBootstrapWizard extends ViewSite
{
    public array $wizardLog = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->server?->connection_type === 'cpanel', 404);
    }

    public function infolist(Schema $schema): Schema
    {
        return CpanelBootstrapWizardInfolist::configure($schema);
    }

    public function getTitle(): string|Htmlable
    {
        return sprintf('%s - cPanel bootstrap wizard', $this->record->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runCpanelBootstrap')
                ->label('Run cPanel bootstrap')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Run the cPanel bootstrap wizard?')
                ->modalDescription('This discovers the SSH port, validates the cPanel API, runs server checks, and bootstraps the deploy path.')
                ->modalSubmitActionLabel('Run bootstrap')
                ->action(fn () => $this->runBootstrapWizard()),
            Action::make('backToSite')
                ->label('Back to site')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('view', [
                    'record' => $this->record,
                ])),
        ];
    }

    protected function runBootstrapWizard(): void
    {
        $this->wizardLog = [];

        try {
            $this->wizardLog = app(CpanelWizardRunner::class)->runSiteBootstrap($this->record->fresh(['server']));

            Notification::make()
                ->title('cPanel bootstrap complete')
                ->body('The server checks passed and the deploy path was bootstrapped successfully.')
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
                ->title('Unable to run cPanel bootstrap')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }
}
