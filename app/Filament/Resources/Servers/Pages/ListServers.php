<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('userGuide')
                ->label('Server Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('Server Management Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.servers.user-guide')),
            CreateAction::make(),
        ];
    }
}
