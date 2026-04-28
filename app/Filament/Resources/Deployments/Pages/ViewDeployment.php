<?php

namespace App\Filament\Resources\Deployments\Pages;

use App\Actions\DeployProject;
use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

class ViewDeployment extends ViewRecord
{
    protected static string $resource = DeploymentResource::class;

    protected function getPollingInterval(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jumpDeployment')
                ->label('Jump to deployment')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->modalWidth('lg')
                ->modalHeading('Jump to another deployment')
                ->modalDescription('Choose a deployment to open it immediately without returning to the table.')
                ->form([
                    Toggle::make('same_site_only')
                        ->label('Only show this site')
                        ->default(true)
                        ->live()
                        ->helperText('Keep this on to browse deployments for the current site only. Turn it off to search all deployments.'),
                    Select::make('deployment_id')
                        ->label('Deployment')
                        ->options(fn (Get $get): array => $this->deploymentJumpOptions((bool) $get('same_site_only')))
                        ->searchable()
                        ->required()
                        ->helperText('Search by site name, branch, commit, or release path.'),
                ])
                ->action(fn (array $data) => $this->jumpToDeployment((int) $data['deployment_id'])),
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->action(fn () => $this->refreshDeployment()),
            Action::make('resume')
                ->label('Resume deployment')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (): bool => $this->record->isResumable())
                ->requiresConfirmation()
                ->modalHeading(function (): string {
                    return sprintf('Resume deployment for %s?', $this->record->site->name);
                })
                ->modalDescription(function (): string {
                    $alreadyUploaded = filled($this->record->archive_uploaded_at)
                        ? 'The uploaded archive will be reused, so the retry will continue from the next incomplete step.'
                        : 'Completed steps will be skipped, and the retry will continue from the next incomplete step.';

                    return $alreadyUploaded.' Confirm only after you have fixed the issue that caused the failure.';
                })
                ->modalSubmitActionLabel('Resume deployment')
                ->action(fn () => $this->queueResume()),
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

    protected function queueResume(): void
    {
        try {
            app(DeployProject::class)->resume($this->record, auth()->user());

            Notification::make()
                ->title('Deployment resumed')
                ->body(sprintf(
                    '%s is resuming from the next incomplete step%s.',
                    $this->record->site->name,
                    filled($this->record->archive_uploaded_at) ? ' and will reuse the already uploaded archive' : '',
                ))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to resume deployment')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function refreshDeployment(): void
    {
        $this->record = $this->record->fresh([
            'site.server',
            'steps',
            'triggeredBy',
        ]);

        $this->dispatch('deployment-refresh');

        Notification::make()
            ->title('Deployment refreshed')
            ->body('The latest deployment status, steps, and logs have been reloaded.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    protected function deploymentJumpOptions(bool $sameSiteOnly = true): array
    {
        $query = Deployment::query()
            ->with(['site'])
            ->visibleInAdmin()
            ->latest('started_at')
            ->latest('id')
            ->limit($sameSiteOnly ? 50 : 100);

        if ($sameSiteOnly) {
            $query->where('site_id', $this->record->site_id);
        }

        return $query
            ->get()
            ->groupBy(fn (Deployment $deployment): string => $deployment->site?->name ?? 'Unknown site')
            ->sortByDesc(function ($deployments): int {
                $latest = $deployments->sortByDesc(fn (Deployment $deployment): int => $deployment->started_at?->timestamp ?? $deployment->id)->first();

                return $latest?->started_at?->timestamp ?? ($latest?->id ?? 0);
            })
            ->map(function ($deployments): array {
                return $deployments
                    ->sortByDesc(fn (Deployment $deployment): int => $deployment->started_at?->timestamp ?? $deployment->id)
                    ->mapWithKeys(function (Deployment $deployment): array {
                        $labelParts = [
                            '#'.$deployment->id,
                            $deployment->started_at?->format('M d H:i')
                                ?? $deployment->created_at?->format('M d H:i')
                                ?? 'just now',
                            str($deployment->status)->headline()->toString(),
                        ];

                        if (filled($deployment->branch)) {
                            $labelParts[] = 'branch: '.$deployment->branch;
                        }

                        if (filled($deployment->commit_hash)) {
                            $labelParts[] = 'commit: '.substr((string) $deployment->commit_hash, 0, 8);
                        }

                        if (filled($deployment->release_path)) {
                            $labelParts[] = basename((string) $deployment->release_path);
                        }

                        return [$deployment->id => implode(' | ', $labelParts)];
                    })
                    ->all();
            })
            ->all();
    }

    protected function jumpToDeployment(int $deploymentId)
    {
        $target = Deployment::query()
            ->visibleInAdmin()
            ->findOrFail($deploymentId);

        return redirect()->to(DeploymentResource::getUrl('view', [
            'record' => $target,
        ]));
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
