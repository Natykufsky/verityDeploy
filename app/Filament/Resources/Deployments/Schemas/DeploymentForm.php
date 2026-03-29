<?php

namespace App\Filament\Resources\Deployments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DeploymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                Select::make('triggered_by_user_id')
                    ->relationship('triggeredBy', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('source')
                    ->required()
                    ->default('manual'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('branch'),
                TextInput::make('commit_hash'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                TextInput::make('exit_code')
                    ->numeric(),
                Textarea::make('output')
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->columnSpanFull(),
            ]);
    }
}
