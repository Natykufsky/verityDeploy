<?php

namespace App\Filament\Resources\ScheduledJobResource\Pages;

use App\Filament\Resources\ScheduledJobResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledJob extends CreateRecord
{
    protected static string $resource = ScheduledJobResource::class;
}