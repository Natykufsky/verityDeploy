<?php

namespace App\Filament\Resources\CredentialProfiles;

use App\Filament\Resources\CredentialProfiles\Pages\CreateCredentialProfile;
use App\Filament\Resources\CredentialProfiles\Pages\EditCredentialProfile;
use App\Filament\Resources\CredentialProfiles\Pages\ListCredentialProfiles;
use App\Filament\Resources\CredentialProfiles\Schemas\CredentialProfileForm;
use App\Filament\Resources\CredentialProfiles\Tables\CredentialProfilesTable;
use App\Models\CredentialProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CredentialProfileResource extends Resource
{
    protected static ?string $model = CredentialProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Credential profiles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CredentialProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CredentialProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCredentialProfiles::route('/'),
            'create' => CreateCredentialProfile::route('/create'),
            'edit' => EditCredentialProfile::route('/{record}/edit'),
        ];
    }
}
