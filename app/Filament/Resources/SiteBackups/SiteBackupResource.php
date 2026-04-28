<?php

namespace App\Filament\Resources\SiteBackups;

use App\Filament\Resources\SiteBackups\Pages\ListSiteBackups;
use App\Filament\Resources\SiteBackups\Pages\ViewSiteBackup;
use App\Filament\Resources\SiteBackups\Schemas\SiteBackupInfolist;
use App\Filament\Resources\SiteBackups\Tables\SiteBackupsTable;
use App\Models\SiteBackup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteBackupResource extends Resource
{
    protected static ?string $model = SiteBackup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 7;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $recordTitleAttribute = 'label';

    public static function infolist(Schema $schema): Schema
    {
        return SiteBackupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteBackupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteBackups::route('/'),
            'view' => ViewSiteBackup::route('/{record}'),
        ];
    }
}
