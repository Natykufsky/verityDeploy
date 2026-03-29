<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Actions\DeployProject;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('repository_url')
                    ->searchable(),
                TextColumn::make('default_branch')
                    ->searchable(),
                TextColumn::make('deploy_path')
                    ->searchable(),
                TextColumn::make('php_version')
                    ->searchable(),
                TextColumn::make('web_root')
                    ->searchable(),
                TextColumn::make('deploy_source')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('last_deployed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('webhook_sync_issues')
                    ->label('Webhook sync issues')
                    ->query(fn ($query) => $query->whereIn('github_webhook_status', ['needs-sync', 'failed']))
                    ->indicateUsing(fn (): array => [
                        Indicator::make('Webhook sync issues'),
                    ]),
            ])
            ->emptyStateHeading(function ($livewire): string {
                return filled($livewire->getTableFilterState('webhook_sync_issues')['isActive'] ?? null)
                    ? 'No webhook issues found'
                    : 'No sites yet';
            })
            ->emptyStateDescription(function ($livewire): ?string {
                return filled($livewire->getTableFilterState('webhook_sync_issues')['isActive'] ?? null)
                    ? 'Every site is currently synced with GitHub. Clear the filter to see the full site list.'
                    : null;
            })
            ->emptyStateActions([
                Action::make('clearWebhookFilter')
                    ->label('Clear filter')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn ($livewire): bool => filled($livewire->getTableFilterState('webhook_sync_issues')['isActive'] ?? null))
                    ->action(fn ($livewire): mixed => $livewire->resetTableFiltersForm()),
            ])
            ->recordActions([
                Action::make('deploy')
                    ->label('Deploy')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Site $record): void {
                        app(DeployProject::class)->dispatch($record, auth()->user());

                        Notification::make()
                            ->title('Deployment queued')
                            ->body("{$record->name} has been queued for deployment.")
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
