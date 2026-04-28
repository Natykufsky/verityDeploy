<?php

namespace App\Filament\Resources\Databases\Pages;

use App\Filament\Resources\Databases\DatabaseResource;
use App\Models\Site;
use App\Services\Databases\DatabaseProvisioner;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditDatabase extends EditRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['site_id'] ?? null)) {
            $data['server_id'] = Site::query()->find($data['site_id'])?->server_id ?? $this->record->server_id;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->status === 'provisioned') {
            return;
        }

        if ($this->record->server?->connection_type !== 'cpanel' || ! filled($this->record->server?->cpanel_api_token)) {
            return;
        }

        try {
            $summary = app(DatabaseProvisioner::class)->provision($this->record->fresh(['server', 'site']));

            Notification::make()
                ->title('Database provisioned')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Database provisioning failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
