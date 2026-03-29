<?php

namespace App\Livewire;

use App\Models\Deployment;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DeploymentTerminal extends Component
{
    public Deployment $record;

    public function render(): View
    {
        $deployment = $this->record->fresh(['site.server', 'steps', 'triggeredBy']) ?? $this->record;

        return view('livewire.deployment-terminal', [
            'deployment' => $deployment,
            'lines' => $this->buildLines($deployment),
        ]);
    }

    /**
     * @return array<int, array{timestamp: string, label: string, status: string, command: string, output: string, duration: string, is_running: bool}>
     */
    protected function buildLines(Deployment $deployment): array
    {
        return $deployment->steps
            ->map(function ($step): array {
                $seconds = $step->started_at && $step->finished_at
                    ? $step->started_at->diffInSeconds($step->finished_at)
                    : ($step->started_at?->diffInSeconds(now()) ?? 0);

                return [
                    'timestamp' => $step->started_at?->format('H:i:s') ?? '--:--:--',
                    'label' => $step->label,
                    'status' => $step->status,
                    'command' => $step->command,
                    'output' => trim((string) $step->output),
                    'duration' => CarbonInterval::seconds(max(0, (int) $seconds))->cascade()->forHumans(short: true),
                    'is_running' => $step->status === 'running',
                ];
            })
            ->all();
    }
}
