<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use App\Services\GitHub\WebhookProvisioner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Throwable;

class WebhookSettings extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected ?string $pollingInterval = '15s';

    public function infolist(Schema $schema): Schema
    {
        return static::getResource()::webhookSettings($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('provisionGitHub')
                ->label('Provision on GitHub')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Provision the GitHub webhook?')
                ->modalDescription('This creates or updates the webhook on GitHub using the endpoint and secret shown on this page.')
                ->action(fn () => $this->provisionWebhook()),
            Action::make('removeGitHubWebhook')
                ->label('Remove webhook')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => filled($this->record->github_webhook_id))
                ->requiresConfirmation()
                ->modalHeading('Disconnect GitHub webhook?')
                ->modalDescription('This will delete the webhook from GitHub and mark the site as needing sync.')
                ->modalIcon('heroicon-o-link-slash')
                ->modalSubmitActionLabel('Disconnect webhook')
                ->action(fn () => $this->removeWebhook()),
            Action::make('regenerateSecret')
                ->label('Regenerate secret')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate the webhook secret?')
                ->modalDescription('This replaces the secret shown on the page, so remember to update GitHub before the next push event.')
                ->action(fn () => $this->regenerateSecret()),
        ];
    }

    protected function regenerateSecret(): void
    {
        try {
            $this->record->update([
                'webhook_secret' => Str::random(48),
                'github_webhook_status' => 'needs-sync',
                'github_webhook_last_error' => null,
            ]);

            Notification::make()
                ->title('Webhook secret regenerated')
                ->body('Copy the new secret into GitHub before pushing again.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to regenerate secret')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function provisionWebhook(): void
    {
        try {
            app(WebhookProvisioner::class)->provision($this->record->fresh());

            Notification::make()
                ->title('GitHub webhook provisioned')
                ->body('GitHub now points to your verityDeploy webhook endpoint.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            $this->record->update([
                'github_webhook_status' => 'failed',
                'github_webhook_last_error' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->title('Unable to provision GitHub webhook')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function removeWebhook(): void
    {
        try {
            app(WebhookProvisioner::class)->remove($this->record->fresh());

            Notification::make()
                ->title('GitHub webhook removed')
                ->body('The remote GitHub webhook has been deleted.')
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            $this->record->update([
                'github_webhook_status' => 'failed',
                'github_webhook_last_error' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->title('Unable to remove GitHub webhook')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }
}
