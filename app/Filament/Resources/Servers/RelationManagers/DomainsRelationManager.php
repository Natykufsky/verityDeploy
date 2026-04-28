<?php

namespace App\Filament\Resources\Servers\RelationManagers;

use App\Models\Domain;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Domains\DomainSslManagementService;
use App\Services\Servers\ServerDomainSynchronizer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('site.name')
                    ->label('Site')
                    ->placeholder('None')
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'primary' => 'success',
                        'addon' => 'gray',
                        'alias' => 'warning',
                        'subdomain' => 'info',
                        default => 'gray',
                    }),
                IconColumn::make('is_ssl_enabled')
                    ->label('SSL')
                    ->boolean(),
                TextColumn::make('ssl_status')
                    ->label('SSL status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'pending' => 'warning',
                        'expired', 'failed' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('No SSL'),
                TextColumn::make('ssl_expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (?string $state): string => $state && now()->parse($state)->isPast() ? 'danger' : 'gray')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('sync')
                    ->label('Sync from server')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->provider_type === 'cpanel')
                    ->action(function (ServerDomainSynchronizer $synchronizer) {
                        $server = $this->getOwnerRecord();
                        $result = $synchronizer->sync($server);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Domains Synced')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('scanSslRenewals')
                    ->label('Scan SSL renewals')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()->domains()->count())
                    ->requiresConfirmation()
                    ->modalHeading('Scan SSL renewals for this server?')
                    ->modalDescription('This checks all tracked SSL certificates on the server and alerts you about expiring or expired domains.')
                    ->modalSubmitActionLabel('Scan renewals')
                    ->action(function (DomainSslManagementService $ssl): void {
                        $summary = $ssl->scanServer($this->getOwnerRecord());

                        Notification::make()
                            ->title('SSL renewal scan finished')
                            ->body(implode(' ', $summary))
                            ->success()
                            ->send();
                    }),
                CreateAction::make(),
            ])
            ->actions([
                Action::make('toggleHttps')
                    ->label(fn (Domain $record): string => ($record->settings['https_redirect'] ?? false) ? 'Disable Force HTTPS' : 'Enable Force HTTPS')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (Domain $record): string => ($record->settings['https_redirect'] ?? false) ? 'danger' : 'success')
                    ->visible(fn (): bool => $this->getOwnerRecord()->provider_type === 'cpanel')
                    ->action(function (Domain $record, CpanelApiClient $cpanel) {
                        $currentState = (bool) ($record->settings['https_redirect'] ?? false);
                        $newState = ! $currentState;

                        try {
                            $cpanel->setHttpsRedirect($this->getOwnerRecord(), $record->name, $newState);

                            $settings = $record->settings ?? [];
                            $settings['https_redirect'] = $newState;
                            $record->update(['settings' => $settings]);

                            Notification::make()
                                ->title($newState ? 'HTTPS Force Enabled' : 'HTTPS Force Disabled')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('autossl')
                    ->label('Run AutoSSL')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->provider_type === 'cpanel')
                    ->requiresConfirmation()
                    ->modalDescription('This will trigger an AutoSSL check on the server for this user. It may take a few minutes to complete.')
                    ->action(function (CpanelApiClient $cpanel) {
                        try {
                            $cpanel->checkAutoSsl($this->getOwnerRecord());
                            Notification::make()
                                ->title('AutoSSL Triggered')
                                ->body('The check has started. Please refresh the list in a few minutes.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
