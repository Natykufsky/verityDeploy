<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\AppSettingsForm;
use App\Models\AppSetting;
use App\Models\AppSettingChange;
use App\Services\AppSettings;
use App\Services\GitHub\GitHubOAuthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class AppSettingsPage extends Page
{
    public ?array $data = [];

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?string $title = 'App Settings';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'app-settings';

    public function mount(): void
    {
        $this->fillForm();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getSettings())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return AppSettingsForm::configure($schema);
    }

    protected function fillForm(): void
    {
        $data = $this->getSettings()->attributesToArray();
        $data['app_logo_path'] = $data['app_logo_path'] ?? null;
        $data['app_favicon_path'] = $data['app_favicon_path'] ?? null;
        $data['app_tagline'] = $data['app_tagline'] ?? null;
        $data['app_description'] = $data['app_description'] ?? null;
        $data['app_support_url'] = $data['app_support_url'] ?? null;
        $data['default_cpanel_api_token'] = null;
        $data['default_dns_api_token'] = null;
        $data['default_webhook_secret'] = null;
        $data['github_api_token'] = null;
        $data['github_oauth_client_secret'] = null;
        $data['alert_webhook_secret'] = null;

        $this->form->fill($data);
    }

    protected function getSettings(): AppSetting
    {
        return app(AppSettings::class)->record();
    }

    public function save(): void
    {
        try {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');
            $data = $this->mutateFormDataBeforeSave($data);
            $this->callHook('beforeSave');

            $settings = $this->getSettings();

            foreach ([
                'app_logo_path',
                'app_favicon_path',
                'app_tagline',
                'app_description',
                'app_support_url',
                'default_ssh_credential_profile_id',
                'default_cpanel_credential_profile_id',
                'default_dns_credential_profile_id',
                'default_webhook_credential_profile_id',
                'github_api_token',
                'github_oauth_client_id',
                'github_oauth_client_secret',
                'default_github_credential_profile_id',
                'alert_webhook_secret',
            ] as $field) {
                if (blank($data[$field] ?? null)) {
                    unset($data[$field]);
                }
            }

            $changes = $this->buildChangeSet($settings, $data);

            $settings->update($data);

            if (filled($changes)) {
                $this->recordChange($settings, $changes);
            }

            $this->callHook('afterSave');
        } catch (Halt $exception) {
            return;
        } catch (Throwable $exception) {
            throw $exception;
        }

        $this->fillForm();

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Your deployment defaults, webhook settings, alert delivery, and GitHub access have been updated.')
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{from: string, to: string}>
     */
    protected function buildChangeSet(AppSetting $settings, array $data): array
    {
        $changes = [];

        foreach ($data as $key => $value) {
            $current = $settings->getAttribute($key);

            if ($this->normalizeSettingValue($current, $key) === $this->normalizeSettingValue($value, $key)) {
                continue;
            }

            $changes[$key] = [
                'from' => $this->maskSettingValue($key, $current),
                'to' => $this->maskSettingValue($key, $value),
            ];
        }

        return $changes;
    }

    protected function recordChange(AppSetting $settings, array $changes): void
    {
        $labels = collect(array_keys($changes))
            ->map(fn (string $key): string => $this->settingLabel($key))
            ->all();

        AppSettingChange::query()->create([
            'app_setting_id' => $settings->id,
            'user_id' => auth()->id(),
            'summary' => 'Updated '.implode(', ', $labels).'.',
            'changes' => $changes,
        ]);
    }

    protected function maskSettingValue(string $key, mixed $value): string
    {
        if (in_array($key, ['github_api_token', 'github_oauth_client_secret', 'github_oauth_access_token'], true)) {
            return filled($value) ? 'updated' : 'cleared';
        }

        if (in_array($key, ['default_cpanel_api_token', 'default_dns_api_token', 'default_webhook_secret'], true)) {
            return filled($value) ? 'updated' : 'cleared';
        }

        if (in_array($key, ['alert_webhook_secret', 'alert_webhook_urls'], true)) {
            return filled($value) ? 'updated' : 'cleared';
        }

        $normalized = $this->normalizeSettingValue($value, $key);

        return filled($normalized) ? $normalized : 'empty';
    }

    protected function normalizeSettingValue(mixed $value, string $key): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    protected function settingLabel(string $key): string
    {
        return match ($key) {
            'app_name' => 'app name',
            'app_logo_path' => 'app logo',
            'app_favicon_path' => 'favicon',
            'app_tagline' => 'app tagline',
            'app_description' => 'app description',
            'app_support_url' => 'support URL',
            'default_branch' => 'default branch',
            'default_web_root' => 'default web root',
            'default_php_version' => 'default PHP version',
            'default_deploy_source' => 'default deploy source',
            'default_ssh_port' => 'default SSH port',
            'default_ssh_credential_profile_id' => 'default SSH profile',
            'default_cpanel_credential_profile_id' => 'default cPanel profile',
            'default_dns_credential_profile_id' => 'default DNS profile',
            'default_webhook_credential_profile_id' => 'default webhook profile',
            'default_github_credential_profile_id' => 'default GitHub profile',
            'github_webhook_path' => 'webhook path',
            'github_webhook_events' => 'webhook events',
            'github_api_token' => 'GitHub PAT',
            'github_oauth_client_id' => 'GitHub OAuth client ID',
            'github_oauth_client_secret' => 'GitHub OAuth client secret',
            'github_oauth_access_token' => 'GitHub OAuth token',
            'github_oauth_connected_at' => 'GitHub OAuth connected at',
            'github_oauth_last_error' => 'GitHub OAuth last error',
            'alert_email_enabled' => 'email alerts',
            'alert_webhooks_enabled' => 'webhook alerts',
            'alert_webhook_urls' => 'webhook URLs',
            'alert_webhook_secret' => 'webhook signing secret',
            default => str_replace('_', ' ', $key),
        };
    }

    protected function settingAnchor(string $key): string
    {
        return match ($key) {
            'app_name' => 'branding-settings',
            'app_logo_path', 'app_favicon_path', 'app_tagline', 'app_description', 'app_support_url' => 'branding-settings',
            'default_branch', 'default_web_root', 'default_php_version', 'default_deploy_source', 'default_ssh_port' => 'deployment-defaults',
            'default_ssh_credential_profile_id', 'default_cpanel_credential_profile_id', 'default_dns_credential_profile_id', 'default_webhook_credential_profile_id' => 'credential-defaults',
            'github_webhook_path', 'github_webhook_events' => 'webhook-defaults',
            'default_github_credential_profile_id', 'github_api_token', 'github_oauth_client_id', 'github_oauth_client_secret', 'github_oauth_access_token' => 'github-integration',
            'alert_email_enabled', 'alert_webhooks_enabled', 'alert_webhook_urls', 'alert_webhook_secret' => 'alert-delivery',
            default => 'branding-settings',
        };
    }

    /**
     * @return array<int, array{timestamp: string, user: string, summary: string, changes: array<string, array{from: string, to: string}>}>
     */
    public function recentChanges(): array
    {
        return AppSettingChange::query()
            ->with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (AppSettingChange $change): array {
                $anchors = collect(array_keys($change->changes ?? []))
                    ->map(fn (string $key): array => [
                        'field' => $this->settingLabel($key),
                        'anchor' => $this->settingAnchor($key),
                    ])
                    ->values()
                    ->all();

                return [
                    'timestamp' => $change->created_at?->format('M j, Y H:i') ?? 'unknown',
                    'user' => $change->user?->name ?? 'System',
                    'summary' => $change->summary,
                    'changes' => $change->changes ?? [],
                    'anchors' => $anchors,
                ];
            })
            ->all();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('connectGitHubOauth')
                ->label('Connect GitHub OAuth')
                ->icon('heroicon-o-globe-alt')
                ->color('primary')
                ->visible(fn (): bool => filled($this->getSettings()->github_oauth_client_id) && filled($this->getSettings()->github_oauth_client_secret))
                ->url(fn (): string => route('github.oauth.redirect'))
                ->openUrlInNewTab(),
            Action::make('disconnectGitHubOauth')
                ->label('Disconnect GitHub OAuth')
                ->icon('heroicon-o-link-slash')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => filled($this->getSettings()->github_oauth_access_token))
                ->modalHeading('Disconnect GitHub OAuth?')
                ->modalDescription('This removes the stored GitHub OAuth token and falls back to the saved PAT or environment token.')
                ->modalSubmitActionLabel('Disconnect OAuth')
                ->action(fn () => $this->disconnectGitHubOauth()),
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('resetBranding')
                ->label('Reset branding')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reset branding?')
                ->modalDescription('This clears the uploaded logo, favicon, tagline, description, and support URL while keeping the app name and the rest of the settings untouched.')
                ->modalSubmitActionLabel('Reset branding')
                ->action(fn () => $this->resetBranding()),
        ];
    }

    protected function resetBranding(): void
    {
        $settings = $this->getSettings();

        $data = [
            'app_logo_path' => null,
            'app_favicon_path' => null,
            'app_tagline' => null,
            'app_description' => null,
            'app_support_url' => null,
        ];

        $changes = $this->buildChangeSet($settings, $data);

        $settings->update($data);

        if (filled($changes)) {
            $this->recordChange($settings, $changes);
        }

        $this->fillForm();

        Notification::make()
            ->success()
            ->title('Branding reset')
            ->body('The logo, favicon, tagline, description, and support URL were cleared.')
            ->send();
    }

    protected function disconnectGitHubOauth(): void
    {
        try {
            app(GitHubOAuthService::class)->disconnect();

            $this->fillForm();

            Notification::make()
                ->success()
                ->title('GitHub OAuth disconnected')
                ->body('Webhook provisioning will use the PAT or environment token again.')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Unable to disconnect GitHub OAuth')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start)
                    ->fullWidth(false)
                    ->sticky(true)
                    ->key('form-actions'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('How this page works')
                    ->schema([
                        View::make('filament.pages.app-settings-guide'),
                    ]),
                Section::make('Global settings')
                    ->extraAttributes(['id' => 'settings-form'])
                    ->schema([
                        $this->getFormContentComponent(),
                    ]),
                Section::make('Settings activity')
                    ->extraAttributes(['id' => 'settings-activity'])
                    ->schema([
                        View::make('filament.pages.app-settings-activity-log')
                            ->viewData(fn (): array => [
                                'changes' => $this->recentChanges(),
                            ]),
                    ]),
            ]);
    }
}
