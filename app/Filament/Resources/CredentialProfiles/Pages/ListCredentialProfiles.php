<?php

namespace App\Filament\Resources\CredentialProfiles\Pages;

use App\Filament\Resources\CredentialProfiles\CredentialProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCredentialProfiles extends ListRecords
{
    protected static string $resource = CredentialProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
