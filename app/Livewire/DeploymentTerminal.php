<?php

namespace App\Livewire;

use App\Models\Deployment;
use App\Services\Deployment\DeploymentBridgeUrl;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DeploymentTerminal extends Component
{
    public Deployment $record;

    public function refreshFromBridge(): void
    {
        $this->record = $this->record->fresh(['site.server', 'steps', 'triggeredBy']) ?? $this->record;
    }

    public function render(): View
    {
        $deployment = $this->record->fresh(['site.server', 'steps', 'triggeredBy']) ?? $this->record;

        return view('livewire.deployment-terminal', [
            'deployment' => $deployment,
            'lines' => $this->buildLines($deployment),
            'bridge' => app(DeploymentBridgeUrl::class)->make($deployment),
        ]);
    }

    /**
     * @return array<int, array{timestamp: string, relative: string, label: string, status: string, command: string, output: string, duration: string, is_running: bool}>
     */
    protected function buildLines(Deployment $deployment): array
    {
        return $deployment->steps
            ->sortByDesc(fn ($step): int => $step->started_at?->timestamp ?? ($step->finished_at?->timestamp ?? $step->sequence))
            ->map(function ($step): array {
                $seconds = $step->started_at && $step->finished_at
                    ? $step->started_at->diffInSeconds($step->finished_at)
                    : ($step->started_at?->diffInSeconds(now()) ?? 0);

                return [
                    'timestamp' => $step->started_at?->format('M d, Y H:i:s') ?? '-- --, ---- --:--:--',
                    'relative' => $step->started_at?->diffForHumans() ?? 'just now',
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
