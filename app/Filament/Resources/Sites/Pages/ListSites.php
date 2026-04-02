<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userGuide')
                ->label('App Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('App Management Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.sites.user-guide')),
            CreateAction::make(),
        ];
    }
}
