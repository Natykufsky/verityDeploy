<?php

namespace App\Filament\Resources\Deployments\Pages;

use App\Actions\DeployProject;
use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewDeployment extends ViewRecord
{
    protected static string $resource = DeploymentResource::class;

    protected ?string $pollingInterval = '5s';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rollback')
                ->label('Rollback')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => filled($this->getRollbackTarget()))
                ->requiresConfirmation()
                ->modalHeading(function (): string {
                    $target = $this->getRollbackTarget();

                    if (! $target) {
                        return 'Rollback to the previous release?';
                    }

                    return sprintf('Rollback to %s?', $target->release_path);
                })
                ->modalDescription(function (): string {
                    $target = $this->getRollbackTarget();

                    return $target
                        ? sprintf(
                            'This will restore %s and replace the current release %s on %s. Confirm only if you want the site pointed back to that exact release path.',
                            $target->release_path,
                            filled($this->record->site->current_release_path) ? $this->record->site->current_release_path : 'none',
                            $this->record->site->name,
                        )
                        : 'No previous successful release was found for this site.';
                })
                ->modalSubmitActionLabel('Rollback to this release')
                ->action(fn () => $this->queueRollback()),
            Action::make('retry')
                ->label('Retry')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === 'failed')
                ->requiresConfirmation()
                ->modalHeading('Retry this deployment?')
                ->modalDescription('This queues the same branch and commit again so you can re-run the failed deploy after fixing the underlying issue.')
                ->action(fn () => $this->queueDeployment(
                    source: 'retry',
                    branch: $this->record->branch,
                    commitHash: $this->record->commit_hash,
                )),
            Action::make('redeploy')
                ->label('Redeploy')
                ->icon('heroicon-o-rocket-launch')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Redeploy this site?')
                ->modalDescription('This creates a fresh deployment record for the current branch and commit so the site can be redeployed intentionally.')
                ->action(fn () => $this->queueDeployment(
                    source: 'manual',
                    branch: $this->record->branch,
                    commitHash: $this->record->commit_hash,
                )),
        ];
    }

    protected function queueDeployment(string $source, ?string $branch = null, ?string $commitHash = null): void
    {
        try {
            app(DeployProject::class)->dispatch(
                $this->record->site,
                auth()->user(),
                $source,
                $commitHash,
                $branch,
            );

            Notification::make()
                ->title('Deployment queued')
                ->body("{$this->record->site->name} has been queued again.")
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to queue deployment')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function queueRollback(): void
    {
        $target = $this->getRollbackTarget();

        if (! $target) {
            Notification::make()
                ->title('No rollback target found')
                ->body('There is no previous successful release to activate.')
                ->warning()
                ->send();

            return;
        }

        try {
            app(DeployProject::class)->rollback($target, auth()->user());

            Notification::make()
                ->title('Rollback queued')
                ->body("{$this->record->site->name} is rolling back to {$target->release_path}.")
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to queue rollback')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getRollbackTarget(): ?Deployment
    {
        return Deployment::query()
            ->where('site_id', $this->record->site_id)
            ->where('status', 'successful')
            ->whereNotNull('release_path')
            ->where('id', '<', $this->record->id)
            ->orderByDesc('id')
            ->first();
    }
}
