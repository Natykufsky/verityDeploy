<?php

namespace App\Filament\Resources\SiteBackups\Pages;

use App\Filament\Resources\SiteBackups\SiteBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListSiteBackups extends ListRecords
{
    protected static string $resource = SiteBackupResource::class;
}
