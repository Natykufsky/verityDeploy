<?php

namespace App\Filament\Resources\Domains;

use App\Filament\Resources\Domains\Pages\ManageDomains;
use App\Models\Domain;
use App\Models\Server;
use App\Services\AppSettings;
use App\Services\Cpanel\CpanelApiClient;
use App\Services\Servers\ServerDomainSynchronizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
                            ->default(fn (): string => app(AppSettings::class)->defaultWebRoot())
                            ->placeholder('public'),
                        TextInput::make('external_id')
                            ->label('Identifier')
                            ->placeholder('Auto-populated'),
                        Toggle::make('is_active')
                            ->label('Active status')
                            ->default(true),
                    ])
                    ->columns(['lg' => 2]),

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
        ];
    }
}
