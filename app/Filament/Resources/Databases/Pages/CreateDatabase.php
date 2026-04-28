<?php

namespace App\Filament\Resources\Databases\Pages;

use App\Filament\Resources\Databases\DatabaseResource;
use App\Models\Site;
use App\Services\Databases\DatabaseProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateDatabase extends CreateRecord
{
    protected static string $resource = DatabaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $site = filled($data['site_id'] ?? null) ? Site::query()->with('server')->find($data['site_id']) : null;

        if ($site) {
            $data['server_id'] = $site->server_id;
        }

        if (blank($data['username'] ?? null)) {
            $data['username'] = $data['name'] ?? 'database';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
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
}
