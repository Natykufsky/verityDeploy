<?php

namespace App\Filament\Pages\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AppSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branding')
                    ->extraAttributes(['id' => 'branding-settings'])
                    ->schema([
                        TextInput::make('app_name')
                            ->required()
                            ->maxLength(120),
                    ])
                    ->columns(1),
                Section::make('Deployment Defaults')
                    ->extraAttributes(['id' => 'deployment-defaults'])
                    ->schema([
                        Select::make('default_deploy_source')
                            ->options([
                                'git' => 'Git',
                                'local' => 'Local',
                            ])
                            ->required(),
                        TextInput::make('default_branch')
                            ->required(),
                        TextInput::make('default_web_root')
                            ->required(),
                        TextInput::make('default_php_version')
                            ->placeholder('8.3'),
                        TextInput::make('default_ssh_port')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Webhook Defaults')
                    ->extraAttributes(['id' => 'webhook-defaults'])
                    ->schema([
                        TextInput::make('github_webhook_path')
                            ->required()
                            ->helperText('The endpoint GitHub should call when push events happen.')
                            ->columnSpanFull(),
                        TextInput::make('github_webhook_events')
                            ->required()
                            ->helperText('Comma-separated GitHub events to subscribe to, such as push.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('GitHub Integration')
                    ->extraAttributes(['id' => 'github-integration'])
                    ->schema([
                        TextInput::make('github_api_token')
                            ->label('GitHub PAT')
                            ->password()
                            ->revealable()
                            ->helperText('Optional fallback if GitHub OAuth is not connected. Leave blank to keep the saved token.')
                            ->columnSpanFull(),
                        TextInput::make('github_oauth_client_id')
                            ->label('GitHub OAuth client ID')
                            ->helperText('Used to connect GitHub without a PAT.')
                            ->columnSpanFull(),
                        TextInput::make('github_oauth_client_secret')
                            ->label('GitHub OAuth client secret')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted. Leave blank to keep the saved secret.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('Alert Delivery')
                    ->extraAttributes(['id' => 'alert-delivery'])
                    ->schema([
                        Toggle::make('alert_email_enabled')
                            ->label('Email alerts')
                            ->helperText('When enabled, operational alerts are emailed to users who can access the Filament panel.'),
                        Toggle::make('alert_webhooks_enabled')
                            ->label('Webhook alerts')
                            ->helperText('When enabled, operational alerts are posted to each URL below as JSON payloads.'),
                        Textarea::make('alert_webhook_urls')
                            ->label('Webhook URLs')
                            ->rows(4)
                            ->placeholder("https://example.com/webhooks/veritydeploy\nhttps://backup.example.com/alerts")
                            ->helperText('Enter one endpoint per line. Leave blank to disable webhook delivery.'),
                        TextInput::make('alert_webhook_secret')
                            ->label('Webhook signing secret')
                            ->password()
                            ->revealable()
                            ->helperText('Optional shared secret used to sign outbound webhook payloads.'),
                    ])
                    ->columns(1),
            ]);
    }
}
