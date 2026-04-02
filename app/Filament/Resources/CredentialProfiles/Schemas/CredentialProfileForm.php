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
                Section::make('Profile details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120),
                        Select::make('type')
                            ->required()
                            ->options(CredentialProfile::typeOptions())
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (filled($state)) {
                                    $set('settings', static::defaultSettings($state));
                                }
                            })
                            ->helperText('Pick the kind of account this profile represents.'),
                        TextInput::make('description')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Optional note explaining what this profile is used for.'),
                        Toggle::make('is_default')
                            ->label('Default profile'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Profile payload')
                    ->schema([
                        KeyValue::make('settings')
                            ->label('Settings')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->helperText('Store the provider-specific values for this profile. Examples: SSH host, username, key path, token, zone ID, webhook URL, or GitHub repository details.'),
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
