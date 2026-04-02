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
            \Filament\Actions\Action::make('userGuide')
                ->label('Credential Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('Credential Management Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.credential-profiles.user-guide')),
            CreateAction::make(),
        ];
    }
}
