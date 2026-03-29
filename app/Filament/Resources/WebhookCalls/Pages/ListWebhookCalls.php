<?php

namespace App\Filament\Resources\WebhookCalls\Pages;

use App\Filament\Resources\WebhookCalls\WebhookCallResource;
use Filament\Resources\Pages\ListRecords;

class ListWebhookCalls extends ListRecords
{
    protected static string $resource = WebhookCallResource::class;
}
