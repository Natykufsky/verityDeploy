<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDomains extends ManageRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userGuide')
                ->label('Domain Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('Domain & SSL Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.domains.user-guide')),
            CreateAction::make(),
        ];
    }
}
