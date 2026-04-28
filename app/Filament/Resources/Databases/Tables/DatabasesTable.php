<?php

namespace App\Filament\Resources\Databases\Tables;

use App\Models\Database;
use App\Services\Databases\DatabaseProvisioner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class DatabasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Database')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Database $record): ?string => $record->cpanelDatabaseName()),
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->placeholder('None'),
                TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable()
                    ->placeholder('None'),
                TextColumn::make('username')
                    ->label('User')
                    ->placeholder('Same as name')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'requested' => 'warning',
                        'provisioning' => 'info',
                        'provisioned' => 'success',
                        'deleted' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('provisioned_at')
                    ->label('Provisioned')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_synced_at')
                    ->label('Synced')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('provision')
                    ->label('Provision')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (Database $record): bool => $record->server?->connection_type === 'cpanel')
                    ->requiresConfirmation()
                    ->action(function (Database $record, DatabaseProvisioner $provisioner): void {
                        try {
                            $summary = $provisioner->provision($record->fresh(['server', 'site']));

                            Notification::make()
                                ->title('Database provisioned')
                                ->body(implode(' ', $summary))
                                ->success()
                                ->send();
                        } catch (Throwable $throwable) {
                            Notification::make()
                                ->title('Provisioning failed')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('removeFromCpanel')
                    ->label('Delete live')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Database $record): bool => $record->server?->connection_type === 'cpanel' && in_array($record->status, ['provisioned', 'failed', 'requested', 'provisioning'], true))
                    ->requiresConfirmation()
                    ->action(function (Database $record, DatabaseProvisioner $provisioner): void {
                        try {
                            $summary = $provisioner->delete($record->fresh(['server', 'site']));

                            Notification::make()
                                ->title('Database removed')
                                ->body(implode(' ', $summary))
                                ->success()
                                ->send();
                        } catch (Throwable $throwable) {
                            Notification::make()
                                ->title('Removal failed')
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
