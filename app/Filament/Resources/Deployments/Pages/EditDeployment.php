<?php

namespace App\Filament\Resources\Deployments\Pages;

use App\Filament\Resources\Deployments\DeploymentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeployment extends EditRecord
{
    protected static string $resource = DeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
