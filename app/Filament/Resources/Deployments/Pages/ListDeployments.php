<?php

namespace App\Filament\Resources\Deployments\Pages;

use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDeployments extends ListRecords
{
    protected static string $resource = DeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pruneStaleFailures')
                ->label('Prune stale failures')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => Deployment::query()->staleFailures()->exists())
                ->requiresConfirmation()
                ->modalHeading('Delete stale failed deployments?')
                ->modalDescription(function (): string {
                    $count = Deployment::query()->staleFailures()->count();

                    return sprintf(
                        'This will permanently delete %d failed deployment record%s older than 30 days and keep the admin list focused on active history.',
                        $count,
                        $count === 1 ? '' : 's',
                    );
                })
                ->modalSubmitActionLabel('Prune failures')
                ->action(function (): void {
                    $count = Deployment::query()->staleFailures()->count();

                    Deployment::query()->staleFailures()->delete();

                    Notification::make()
                        ->title('Stale failures pruned')
                        ->body($count > 0
                            ? sprintf('%d stale failed deployment record%s were removed from the admin UI.', $count, $count === 1 ? '' : 's')
                            : 'No stale failed deployments were found.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
