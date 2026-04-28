<?php

namespace App\Filament\Resources\SiteBackups\Tables;

use App\Filament\Resources\SiteBackups\Pages\ViewSiteBackup;
use App\Models\SiteBackup;
use App\Services\Backups\SiteBackupService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class SiteBackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('site.name')->label('Site')->searchable()->sortable(),
                TextColumn::make('operation')->badge(),
                TextColumn::make('status')->badge()->color(fn (?string $state): string => match ($state) {
                    'successful' => 'success',
                    'running' => 'info',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('label')->placeholder('No label')->searchable(),
                TextColumn::make('started_at')->label('Started')->dateTime()->sortable(),
                TextColumn::make('finished_at')->label('Finished')->dateTime()->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (SiteBackup $record): bool => $record->operation === 'backup' && $record->status === 'successful')
                    ->requiresConfirmation()
                    ->action(function (SiteBackup $record): void {
                        try {
                            app(SiteBackupService::class)->restore($record->fresh(['site.server']), auth()->user());

                            Notification::make()
                                ->title('Restore queued')
                                ->body('The selected backup is now being restored.')
                                ->success()
                                ->send();
                        } catch (Throwable $throwable) {
                            Notification::make()
                                ->title('Restore failed')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
