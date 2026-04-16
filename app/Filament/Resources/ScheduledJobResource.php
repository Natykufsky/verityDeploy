<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduledJobResource\Pages;
use App\Models\ScheduledJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScheduledJobResource extends Resource
{
    protected static ?string $model = ScheduledJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Sites';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('command')
                    ->required()
                    ->placeholder('php artisan queue:work'),
                Forms\Components\TextInput::make('frequency')
                    ->required()
                    ->placeholder('* * * * *')
                    ->helperText('Cron expression (e.g., "0 * * * *" for hourly)'),
                Forms\Components\Textarea::make('description')
                    ->rows(2),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('command')
                    ->searchable(),
                Tables\Columns\TextColumn::make('frequency'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledJobs::route('/'),
            'create' => Pages\CreateScheduledJob::route('/create'),
            'edit' => Pages\EditScheduledJob::route('/{record}/edit'),
        ];
    }
}