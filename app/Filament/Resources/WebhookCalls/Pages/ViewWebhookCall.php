<?php

namespace App\Filament\Resources\WebhookCalls\Pages;

use App\Filament\Resources\WebhookCalls\WebhookCallResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWebhookCall extends ViewRecord
{
    protected static string $resource = WebhookCallResource::class;

    protected ?string $pollingInterval = '10s';
}
