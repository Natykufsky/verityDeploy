<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Site;
use App\Services\Cpanel\CpanelInventoryRepairService;
use App\Services\Databases\SiteDatabaseSynchronizer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->generateDeploymentPath($data);
    }

    protected function afterSave(): void
    {
        if ($this->record->create_database || $this->record->wasChanged(['create_database', 'database_name'])) {
            app(SiteDatabaseSynchronizer::class)->sync($this->record->fresh(['server', 'database']));
        }

        if (! $this->record->wasChanged([
            'primary_domain_id',
            'deploy_path',
            'web_root',
            'subdomains',
            'alias_domains',
            'force_https',
            'ssl_state',
        ])) {
            return;
        }

        if ($this->record->server?->connection_type !== 'cpanel' || blank($this->record->primary_domain)) {
            return;
        }

        try {
            $summary = app(CpanelInventoryRepairService::class)->repair($this->record->fresh(['server']));

            Notification::make()
                ->title('Site synced')
                ->body(implode(' ', $summary))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Site sync failed')
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
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function generateDeploymentPath(array $data): array
    {
        $server = filled($data['server_id'] ?? null) ? Server::query()->find($data['server_id']) : $this->record->server;
        $domain = filled($data['primary_domain_id'] ?? null) ? Domain::query()->find($data['primary_domain_id']) : $this->record->primaryDomain;

        if ($server && $domain) {
            $data['deploy_path'] = Site::deriveDeployPathFromDomain($server, $domain->name);
            $data['web_root'] = 'public';
        }

        return $data;
    }
}
