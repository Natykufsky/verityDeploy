<?php

namespace App\Filament\Resources\Domains;

use App\Filament\Resources\Domains\Pages\EditDomain;
use App\Filament\Resources\Domains\Pages\ManageDomains;
use App\Filament\Resources\Domains\Schemas\DomainInfolist;
use App\Models\Domain;
use App\Models\Server;
use App\Services\AppSettings;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Domains\DomainSslManagementService;
use App\Services\Servers\ServerDomainSynchronizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Domain details')
                    ->schema([
                        Select::make('server_id')
                            ->relationship('server', 'name')
                            ->live()
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('site_id')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('No site link'),
                        TextInput::make('name')
                            ->label('Domain name')
                            ->placeholder('e.g. app.example.com')
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Select::make('type')
                            ->options([
                                'primary' => 'Primary domain',
                                'addon' => 'Addon domain (cPanel)',
                                'alias' => 'Alias / Parked',
                                'subdomain' => 'Subdomain',
                            ])
                            ->default('primary')
                            ->required(),
                    ])
                    ->columns(['lg' => 2]),

                Section::make('Routing & Configuration')
                    ->schema([
                        Select::make('php_version')
                            ->options(['8.1' => '8.1', '8.2' => '8.2', '8.3' => '8.3', '8.4' => '8.4', 'inherit' => 'Inherit from server'])
                            ->default('inherit')
                            ->placeholder(fn (): string => app(AppSettings::class)->defaultPhpVersion() ?? '8.3'),
                        TextInput::make('web_root')
                            ->label('cPanel document root')
                            ->default(fn (): string => app(AppSettings::class)->defaultWebRoot())
                            ->placeholder('public')
                            ->helperText('This value is pushed directly to cPanel as the live document root.'),
                        TextInput::make('external_id')
                            ->label('Identifier')
                            ->placeholder('Auto-populated'),
                        Toggle::make('is_active')
                            ->label('Active status')
                            ->default(true),
                    ])
                    ->columns(['lg' => 2]),

                Section::make('Sync mode')
                    ->description('This tells you which save actions will update cPanel immediately and which ones stay as local database changes.')
                    ->schema([
                        Placeholder::make('sync_mode')
                            ->label('Live sync behavior')
                            ->content(fn (Get $get): string => match ($get('type')) {
                                'primary' => 'Primary domains are provisioned through the site workflow so cPanel stays aligned with the site record.',
                                'addon' => 'Addon domains sync to cPanel when saved, and their directory updates are pushed live on supported accounts.',
                                'subdomain' => 'Subdomains sync to cPanel when saved, including live document-root changes.',
                                'alias' => 'Alias domains sync to cPanel when saved and are removed or recreated as needed.',
                                default => 'Choose a domain type to see how this record syncs with cPanel.',
                            }),
                    ]),

                Section::make('SSL Security')
                    ->schema([
                        Grid::make(['lg' => 3])
                            ->schema([
                                Toggle::make('is_ssl_enabled')
                                    ->label('Manage SSL')
                                    ->live(),
                                Toggle::make('settings.https_redirect')
                                    ->label('Force HTTPS')
                                    ->live(),
                                Select::make('ssl_status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'issued' => 'Issued',
                                        'expired' => 'Expired',
                                        'failed' => 'Failed',
                                    ])
                                    ->placeholder('Unknown')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_ssl_enabled')),
                            ]),
                        DateTimePicker::make('ssl_expires_at')
                            ->visible(fn (Get $get): bool => (bool) $get('is_ssl_enabled')),
                        Tabs::make('Manual Certificate Data')
                            ->tabs([
                                Tab::make('Cert')
                                    ->schema([Textarea::make('ssl_certificate')->rows(8)]),
                                Tab::make('Key')
                                    ->schema([Textarea::make('ssl_key')->rows(8)]),
                                Tab::make('Bundle')
                                    ->schema([Textarea::make('ssl_chain')->rows(8)]),
                            ])
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => (bool) $get('is_ssl_enabled')),
                    ])
                    ->columns(['default' => 1])
                    ->collapsible(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DomainInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Domain $record): ?string => $record->server?->name),
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
                    }),
                IconColumn::make('is_ssl_enabled')
                    ->label('SSL')
                    ->boolean(),
                TextColumn::make('ssl_status')
                    ->label('SSL status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
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
                    ->color(fn ($state) => $state && now()->parse($state)->isPast() ? 'danger' : 'gray')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('php_version')
                    ->label('PHP')
                    ->placeholder('Def')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('importFromServer')
                    ->label('Sync from server')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->form([
                        Select::make('server_id')
                            ->label('Select Server')
                            ->options(function () {
                                $user = auth()->user();

                                return Server::query()
                                    ->where('provider_type', 'cpanel')
                                    ->where(function (Builder $query) use ($user) {
                                        $query->where('user_id', $user->id);

                                        if ($teamId = $user->current_team_id ?? null) {
                                            $query->orWhere('team_id', $teamId);
                                        }
                                    })
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, ServerDomainSynchronizer $synchronizer) {
                        $server = Server::find($data['server_id']);
                        if (! $server) {
                            return;
                        }

                        $result = $synchronizer->sync($server);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Sync Complete')
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
            ])
            ->recordActions([
                Action::make('toggleHttps')
                    ->label(fn (Domain $record): string => ($record->settings['https_redirect'] ?? false) ? 'Disable Force HTTPS' : 'Enable Force HTTPS')
                    ->icon('heroicon-o-shield-check')
                    ->color(fn (Domain $record): string => ($record->settings['https_redirect'] ?? false) ? 'danger' : 'success')
                    ->visible(fn (Domain $record): bool => $record->server?->provider_type === 'cpanel')
                    ->action(function (Domain $record, CpanelApiClient $cpanel) {
                        $currentState = (bool) ($record->settings['https_redirect'] ?? false);
                        $newState = ! $currentState;
                        try {
                            $cpanel->setHttpsRedirect($record->server, $record->name, $newState);
                            $settings = $record->settings ?? [];
                            $settings['https_redirect'] = $newState;
                            $record->update(['settings' => $settings]);
                            Notification::make()->title($newState ? 'HTTPS Forced' : 'HTTPS Normal')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Action Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('autossl')
                    ->label('Run AutoSSL')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->visible(fn (Domain $record): bool => $record->server?->provider_type === 'cpanel')
                    ->requiresConfirmation()
                    ->action(function (Domain $record, CpanelApiClient $cpanel) {
                        try {
                            $cpanel->checkAutoSsl($record->server);
                            Notification::make()->title('AutoSSL Triggered')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Action Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('markSslIssued')
                    ->label('Mark SSL issued')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (Domain $record): bool => filled($record->ssl_certificate) || filled($record->ssl_key))
                    ->modalHeading('Mark the SSL certificate as issued?')
                    ->modalDescription('Use this when you have pasted a certificate bundle and want to track its expiry on the domain record.')
                    ->schema([
                        DateTimePicker::make('ssl_expires_at')
                            ->label('Expires at')
                            ->helperText('Leave blank to default to 90 days from now.'),
                    ])
                    ->modalSubmitActionLabel('Mark issued')
                    ->action(function (Domain $record, array $data, DomainSslManagementService $ssl): void {
                        try {
                            $summary = $ssl->markIssued(
                                $record,
                                filled($data['ssl_expires_at'] ?? null) ? now()->parse($data['ssl_expires_at']) : null,
                            );

                            Notification::make()
                                ->title('SSL marked issued')
                                ->body(implode(' ', $summary))
                                ->success()
                                ->send();
                        } catch (\Throwable $throwable) {
                            Notification::make()
                                ->title('Unable to mark SSL issued')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('markSslRenewalDue')
                    ->label('Mark renewal due')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Domain $record): bool => (bool) $record->is_ssl_enabled)
                    ->modalHeading('Mark SSL renewal as due?')
                    ->modalDescription('This marks the SSL record as needing renewal so the renewal tracker can surface it clearly.')
                    ->modalSubmitActionLabel('Mark due')
                    ->action(function (Domain $record, DomainSslManagementService $ssl): void {
                        try {
                            $summary = $ssl->markRenewalDue($record);

                            Notification::make()
                                ->title('SSL marked for renewal')
                                ->body(implode(' ', $summary))
                                ->warning()
                                ->send();
                        } catch (\Throwable $throwable) {
                            Notification::make()
                                ->title('Unable to mark renewal due')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('clearSslTracking')
                    ->label('Clear SSL data')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Domain $record): bool => filled($record->ssl_certificate) || filled($record->ssl_key) || filled($record->ssl_chain) || filled($record->ssl_status))
                    ->modalHeading('Clear the SSL tracking data?')
                    ->modalDescription('This removes the stored certificate material, expiry date, and SSL status from the domain record.')
                    ->modalSubmitActionLabel('Clear SSL data')
                    ->action(function (Domain $record, DomainSslManagementService $ssl): void {
                        try {
                            $summary = $ssl->clearTracking($record);

                            Notification::make()
                                ->title('SSL data cleared')
                                ->body(implode(' ', $summary))
                                ->success()
                                ->send();
                        } catch (\Throwable $throwable) {
                            Notification::make()
                                ->title('Unable to clear SSL data')
                                ->body($throwable->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereHas('server', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);

                if ($teamId = $user->current_team_id ?? null) {
                    $query->orWhere('team_id', $teamId);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDomains::route('/'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }
}
