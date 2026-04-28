<?php

namespace App\Filament\Resources\ScheduledJobResource\Pages;

use App\Filament\Resources\ScheduledJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScheduledJobs extends ListRecords
{
    protected static string $resource = ScheduledJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
