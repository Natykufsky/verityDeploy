<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DeploymentListGuide extends Widget
{
    protected string $view = 'filament.widgets.deployment-list-guide';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -10;
}
