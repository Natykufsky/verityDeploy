<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardGuide extends Widget
{
    protected string $view = 'filament.pages.dashboard-guide';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;
}
