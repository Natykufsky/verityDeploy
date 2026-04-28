<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduledJobResource\Pages;
use App\Models\ScheduledJob;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimeInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use BackedEnum;

class ScheduledJobResource extends Resource
{
    protected static ?string $model = ScheduledJob::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Scheduled Jobs';

    protected static string|\UnitEnum|null $navigationGroup = 'Deployments';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('command')
                    ->required()
                    ->placeholder('e.g., php artisan schedule:run')
                    ->helperText('The command to execute on the scheduled frequency'),

                Select::make('frequency')
                    ->options([
                        'hourly' => 'Hourly',
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->required(),

                Textarea::make('description')
                    ->maxLength(500)
                    ->helperText('Optional description of what this job does'),

                Checkbox::make('is_active')
                    ->default(true),

                DateTimeInput::make('last_run_at')
                    ->disabled(),

                DateTimeInput::make('next_run_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('command')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('frequency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hourly' => 'info',
                        'daily' => 'success',
                        'weekly' => 'warning',
                        'monthly' => 'danger',
                        'yearly' => 'gray',
                        default => 'gray',
                    }),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('next_run_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
