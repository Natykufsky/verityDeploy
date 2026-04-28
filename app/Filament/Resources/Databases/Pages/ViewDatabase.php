<?php

namespace App\Filament\Resources\Databases\Pages;

use App\Filament\Resources\Databases\DatabaseResource;
use App\Services\Databases\DatabaseProvisioner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewDatabase extends ViewRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->record->refresh()),
            Action::make('provision')
                ->label('Provision on cPanel')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $summary = app(DatabaseProvisioner::class)->provision($this->record->fresh(['server', 'site']));

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
            Action::make('deleteLive')
                ->label('Delete live database')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->record->server?->connection_type === 'cpanel')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        $summary = app(DatabaseProvisioner::class)->delete($this->record->fresh(['server', 'site']));

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
        ];
    }
}
