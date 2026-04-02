<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Actions\DeployProject;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->accessibleTo())
            ->columns([
                TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('Inherited')
                    ->searchable(),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('repository_url')
                    ->label('Repository')
                    ->icon('heroicon-m-link')
                    ->color('primary')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('default_branch')
                    ->label('Branch')
                    ->badge()
                    ->icon('heroicon-m-code-bracket')
                    ->color('info')
                    ->searchable(),
                TextColumn::make('deploy_path')
                    ->label('Path')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('deploy_source')
                    ->label('Source')
                    ->badge()
                    ->icon(fn (string $state): string => $state === 'git' ? 'heroicon-m-cloud-arrow-down' : 'heroicon-m-computer-desktop')
                    ->color(fn (string $state): string => $state === 'git' ? 'primary' : 'gray')
                    ->searchable(),
                IconColumn::make('active')
                    ->label('Online')
                    ->boolean()
                    ->trueIcon('heroicon-s-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                TextColumn::make('last_deployed_at')
                    ->label('Last Deploy')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->color('gray')
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
                ActionGroup::make([
                    Action::make('deploy')
                        ->label('Deploy now')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Site $record): bool => $record->active && filled($record->repository_url))
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
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
