<?php

namespace App\Filament\Pages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AlertPreferencesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Notification preferences')
                    ->extraAttributes(['id' => 'alert-preferences'])
                    ->schema([
                        Toggle::make('alert_inbox_enabled')
                            ->label('In-app alerts')
                            ->helperText('Keep operational alerts in your Filament inbox.'),
                        Toggle::make('alert_email_enabled')
                            ->label('Email alerts')
                            ->helperText('Receive a copy of operational alerts by email when the global alert delivery setting is enabled.'),
                        Select::make('alert_minimum_level')
                            ->label('Minimum alert level')
                            ->options([
                                'warning' => 'Warning and above',
                                'danger' => 'Danger only',
                            ])
                            ->required()
                            ->helperText('Choose the lowest severity you want to receive.'),
                    ])
                    ->columns(1),
            ]);
    }
}
