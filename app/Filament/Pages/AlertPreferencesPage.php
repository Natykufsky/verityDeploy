<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Schemas\AlertPreferencesForm;
use App\Models\User;
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
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

class AlertPreferencesPage extends Page
{
    public ?array $data = [];

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected static ?string $navigationLabel = 'Alert Preferences';

    protected static ?string $title = 'Alert Preferences';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $slug = 'alert-preferences';

    public function mount(): void
    {
        $this->fillForm();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->user())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return AlertPreferencesForm::configure($schema);
    }

    protected function user(): User
    {
        return auth()->user();
    }

    protected function fillForm(): void
    {
        $data = $this->user()->attributesToArray();

        $this->form->fill([
            'alert_inbox_enabled' => (bool) ($data['alert_inbox_enabled'] ?? true),
            'alert_email_enabled' => (bool) ($data['alert_email_enabled'] ?? true),
            'alert_minimum_level' => (string) ($data['alert_minimum_level'] ?? 'warning'),
        ]);
    }

    public function save(): void
    {
        try {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');
            $data = $this->mutateFormDataBeforeSave($data);
            $this->callHook('beforeSave');

            $this->user()->update($data);

            $this->callHook('afterSave');
        } catch (Throwable $exception) {
            throw $exception;
        }

        $this->fillForm();

        Notification::make()
            ->success()
            ->title('Alert preferences saved')
            ->body('Your inbox and email alert preferences were updated.')
            ->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save preferences')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
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
                        View::make('filament.pages.alert-preferences-guide'),
                    ]),
                Section::make('My alert preferences')
                    ->schema([
                        $this->getFormContentComponent(),
                    ]),
            ]);
    }
}
