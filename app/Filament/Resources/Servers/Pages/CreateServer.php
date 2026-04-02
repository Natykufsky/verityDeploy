<?php

namespace App\Filament\Resources\Servers\Pages;

use App\Filament\Resources\Servers\ServerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userGuide')
                ->label('Server Guide')
                ->icon('heroicon-m-book-open')
                ->color('info')
                ->modalHeading('Server Management Guide')
                ->modalWidth('2xl')
                ->modalFooterActions([])
                ->modalContent(view('filament.servers.user-guide')),
        ];
    }
}
