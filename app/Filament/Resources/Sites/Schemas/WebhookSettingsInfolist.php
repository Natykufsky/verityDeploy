<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Services\AppSettings;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebhookSettingsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('GitHub Registration')
                    ->schema([
                        TextEntry::make('repository_url')
                            ->label('Repository URL')
                            ->copyable(),
                        TextEntry::make('default_branch')
                            ->label('Branch')
                            ->badge(),
                        TextEntry::make('webhook_endpoint')
                            ->label('Webhook endpoint')
                            ->state(fn (): string => url(app(AppSettings::class)->githubWebhookPath()))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('webhook_secret')
                            ->label('Webhook secret')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('github_webhook_id')
                            ->label('GitHub webhook ID'),
                        TextEntry::make('github_webhook_status')
                            ->label('Provisioning status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'provisioned' => 'success',
                                'needs-sync' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('github_webhook_synced_at')
                            ->label('Last synced')
                            ->dateTime(),
                        TextEntry::make('github_webhook_last_error')
                            ->label('Last error')
                            ->columnSpanFull(),
                        TextEntry::make('webhook_event')
                            ->label('Event')
                            ->state(fn (): string => implode(', ', app(AppSettings::class)->githubWebhookEvents()))
                            ->badge(),
                        TextEntry::make('content_type')
                            ->label('Content type')
                            ->state('application/json'),
                        TextEntry::make('signature_header')
                            ->label('Signature header')
                            ->state('X-Hub-Signature-256'),
                        TextEntry::make('registration_checklist')
                            ->label('Registration checklist')
                            ->state(fn (): array => [
                                'Open your repository on GitHub.',
                                'Create a new webhook using the endpoint above.',
                                'Set the secret to the value shown here.',
                                'Select the configured event set shown above.',
                                'Leave SSL verification enabled.',
                            ])
                            ->bulleted()
                            ->listWithLineBreaks()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Deployment Mapping')
                    ->schema([
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('name')
                            ->label('Site'),
                        TextEntry::make('deploy_source')
                            ->badge(),
                        TextEntry::make('deploy_path')
                            ->copyable(),
                        TextEntry::make('web_root'),
                        TextEntry::make('last_deployed_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
