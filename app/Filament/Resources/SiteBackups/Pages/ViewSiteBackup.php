<?php

namespace App\Filament\Resources\SiteBackups\Pages;

use App\Filament\Resources\SiteBackups\SiteBackupResource;
use App\Services\Backups\SiteBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewSiteBackup extends ViewRecord
{
    protected static string $resource = SiteBackupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->record->refresh()),
            Action::make('restore')
                ->label('Restore backup')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->operation === 'backup' && $this->record->status === 'successful')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(SiteBackupService::class)->restore($this->record->fresh(['site.server']), auth()->user());

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
        ];
    }
}
