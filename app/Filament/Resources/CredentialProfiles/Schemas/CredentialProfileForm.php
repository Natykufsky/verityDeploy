<?php

namespace App\Filament\Resources\CredentialProfiles\Schemas;

use App\Models\CredentialProfile;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CredentialProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile definition')
                    ->description('Assign a type and name to this shared credential set.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Profile name')
                            ->placeholder('e.g. Production GitHub')
                            ->required()
                            ->maxLength(120),
                        Select::make('type')
                            ->label('Credential type')
                            ->required()
                            ->options(CredentialProfile::typeOptions())
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (filled($state)) {
                                    $set('settings', static::defaultSettings($state));
                                }
                            }),
                        TextInput::make('description')
                            ->label('Context / Notes')
                            ->placeholder('Where and why is this used?')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_default')
                            ->label('Mark as system default')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Enable this profile')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Secure payload')
                    ->description('Store provider-specific key-value pairs here.')
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Configuration values')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultSettings(string $type): array
    {
        return match ($type) {
            'ssh' => [
                'username' => 'root',
                'port' => '22',
                'private_key' => '',
                'sudo_password' => '',
            ],
            'cpanel' => [
                'username' => '',
                'api_token' => '',
                'api_port' => '2083',
            ],
            'github' => [
                'api_token' => '',
                'username' => '',
                'repository' => '',
            ],
            'dns' => [
                'provider' => 'cloudflare',
                'api_token' => '',
                'zone_id' => '',
                'proxy_records' => '1',
            ],
            'webhook' => [
                'webhook_url' => '',
                'secret' => '',
            ],
            default => [],
        };
    }
}
