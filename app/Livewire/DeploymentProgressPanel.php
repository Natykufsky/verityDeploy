<?php

namespace App\Livewire;

use App\Models\Deployment;
use App\Services\Deployment\DeploymentBridgeUrl;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DeploymentProgressPanel extends Component
{
    public Deployment $record;

    public function refreshFromBridge(): void
    {
        $this->record = $this->record->fresh(['site.server', 'steps', 'triggeredBy']) ?? $this->record;
    }

    public function render(): View
    {
        $deployment = $this->record->fresh(['site.server', 'steps', 'triggeredBy']) ?? $this->record;

        return view('filament.deployments.deployment-progress', [
            'record' => $deployment,
            'bridge' => app(DeploymentBridgeUrl::class)->make($deployment),
        ]);
    }
}
