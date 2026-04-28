<?php

namespace App\Filament\Resources\Databases;

use App\Filament\Resources\Databases\Pages\CreateDatabase;
use App\Filament\Resources\Databases\Pages\EditDatabase;
use App\Filament\Resources\Databases\Pages\ListDatabases;
use App\Filament\Resources\Databases\Pages\ViewDatabase;
use App\Filament\Resources\Databases\Schemas\DatabaseForm;
use App\Filament\Resources\Databases\Schemas\DatabaseInfolist;
use App\Filament\Resources\Databases\Tables\DatabasesTable;
use App\Models\Database;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DatabaseResource extends Resource
{
    protected static ?string $model = Database::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DatabaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DatabaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DatabasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDatabases::route('/'),
            'create' => CreateDatabase::route('/create'),
            'view' => ViewDatabase::route('/{record}'),
            'edit' => EditDatabase::route('/{record}/edit'),
        ];
    }
}
