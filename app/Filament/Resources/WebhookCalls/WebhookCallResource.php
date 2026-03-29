<?php

namespace App\Filament\Resources\WebhookCalls;

use App\Filament\Resources\WebhookCalls\Pages\ListWebhookCalls;
use App\Filament\Resources\WebhookCalls\Pages\ViewWebhookCall;
use App\Filament\Resources\WebhookCalls\Schemas\WebhookCallInfolist;
use App\Filament\Resources\WebhookCalls\Tables\WebhookCallsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\WebhookClient\Models\WebhookCall;

class WebhookCallResource extends Resource
{
    protected static ?string $model = WebhookCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return WebhookCallInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebhookCallsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookCalls::route('/'),
            'view' => ViewWebhookCall::route('/{record}'),
        ];
    }
}
