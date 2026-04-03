<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelInventoryRepairService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->generateDeploymentPath($data);
    }

    protected function afterCreate(): void
    {
        if ($this->record->server?->connection_type !== 'cpanel' || blank($this->record->primary_domain)) {
            return;
        }

        try {
            $summary = app(CpanelInventoryRepairService::class)->repair($this->record->fresh(['server']));

            Notification::make()
                ->title('Site provisioned')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Site provisioning failed')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userGuide')
                ->label('App Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('App Management Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.sites.user-guide')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function generateDeploymentPath(array $data): array
    {
        $server = filled($data['server_id'] ?? null) ? Server::query()->find($data['server_id']) : null;
        $domain = filled($data['primary_domain_id'] ?? null) ? Domain::query()->find($data['primary_domain_id']) : null;

        if ($server && $domain) {
            $data['deploy_path'] = Site::deriveDeployPathFromDomain($server, $domain->name);
            $data['web_root'] = 'public';
        }

        return $data;
    }
}
