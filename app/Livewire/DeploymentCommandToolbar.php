<?php

namespace App\Livewire;

use App\Models\Deployment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DeploymentCommandToolbar extends Component
{
    public Deployment $record;

    public function render(): View
    {
        $deployment = $this->record->fresh(['site']) ?? $this->record;

        return view('livewire.deployment-command-toolbar', [
            'deployment' => $deployment,
            'snippets' => collect($deployment->command_guide_snippets)->take(4)->values()->all(),
        ]);
    }
}
