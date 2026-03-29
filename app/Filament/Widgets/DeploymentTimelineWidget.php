<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Deployments\DeploymentResource;
use App\Models\Deployment;
use Filament\Widgets\Widget;

class DeploymentTimelineWidget extends Widget
{
    protected string $view = 'filament.widgets.deployment-timeline-widget';

    protected ?string $pollingInterval = '30s';

    public ?int $selectedDeploymentId = null;

    public ?int $selectedStepSequence = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $latestDeployment = Deployment::query()
            ->with(['site', 'steps'])
            ->visibleInAdmin()
            ->latest('started_at')
            ->latest('id')
            ->first();

        return [
            'latestDeploymentId' => $latestDeployment?->id,
            'latestDeployment' => $latestDeployment,
            'latestDeploymentLabel' => $this->deploymentLabel($latestDeployment),
            'latestDeploymentTone' => $this->deploymentTone($latestDeployment),
            'latestDeploymentWhen' => $this->deploymentWhen($latestDeployment),
            'latestDeploymentSummary' => $this->deploymentSummary($latestDeployment),
            'latestDeploymentUrl' => $this->deploymentUrl($latestDeployment),
            'stepChips' => $this->stepChips($latestDeployment),
            'selectedStepDetail' => $this->selectedStepDetail(),
            'successfulCount' => Deployment::query()->where('status', 'successful')->count(),
            'failedCount' => Deployment::query()->where('status', 'failed')->count(),
            'runningCount' => Deployment::query()->where('status', 'running')->count(),
        ];
    }

    public function openStepDetail(int $deploymentId, int $sequence): void
    {
        $this->selectedDeploymentId = $deploymentId;
        $this->selectedStepSequence = $sequence;
    }

    public function closeStepDetail(): void
    {
        $this->selectedDeploymentId = null;
        $this->selectedStepSequence = null;
    }

    public function openLatestDeployment()
    {
        $latestDeployment = Deployment::query()
            ->with(['site', 'steps'])
            ->visibleInAdmin()
            ->latest('started_at')
            ->latest('id')
            ->first();

        return redirect()->to($this->deploymentUrl($latestDeployment) ?? DeploymentResource::getUrl('index'));
    }

    protected function deploymentLabel(?Deployment $deployment): string
    {
        if (! $deployment) {
            return 'No deployments yet';
        }

        return $deployment->site?->name ?? 'Unknown site';
    }

    protected function deploymentTone(?Deployment $deployment): string
    {
        if (! $deployment) {
            return 'slate';
        }

        return match ($deployment->status) {
            'successful' => 'emerald',
            'failed' => 'rose',
            'running' => 'amber',
            default => 'slate',
        };
    }

    protected function deploymentWhen(?Deployment $deployment): string
    {
        return $deployment?->finished_at?->diffForHumans()
            ?? $deployment?->started_at?->diffForHumans()
            ?? 'just now';
    }

    protected function deploymentSummary(?Deployment $deployment): string
    {
        if (! $deployment) {
            return 'Deployment steps will appear here after the first queued deployment.';
        }

        $parts = [
            $deployment->source ? 'Source: '.str($deployment->source)->headline() : null,
            $deployment->branch ? 'Branch: '.$deployment->branch : null,
            $deployment->release_path ? 'Release: '.$deployment->release_path : null,
            $deployment->commit_hash ? 'Commit: '.$deployment->commit_hash : null,
        ];

        return implode(' | ', array_values(array_filter($parts))) ?: 'Deployment summary unavailable.';
    }

    /**
     * @return array<int, array{label: string, tone: string, detail: string}>
     */
    protected function stepChips(?Deployment $deployment): array
    {
        if (! $deployment) {
            return [];
        }

        return $deployment->steps
            ->sortBy('sequence')
            ->map(function ($step): array {
                return [
                    'sequence' => $step->sequence,
                    'label' => $step->label,
                    'status' => str($step->status)->headline()->toString(),
                    'tone' => match ($step->status) {
                        'successful' => 'emerald',
                        'failed' => 'rose',
                        'running' => 'amber',
                        default => 'slate',
                    },
                    'detail' => trim((string) $step->output) ?: trim((string) $step->command),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectedStepDetail(): ?array
    {
        if (! filled($this->selectedDeploymentId) || ! filled($this->selectedStepSequence)) {
            return null;
        }

        $deployment = Deployment::query()
            ->with(['site', 'steps'])
            ->find($this->selectedDeploymentId);

        $step = $deployment?->steps->firstWhere('sequence', $this->selectedStepSequence);

        if (! $deployment || ! $step) {
            return null;
        }

        return [
            'deployment_label' => $deployment->site?->name ?? 'Unknown site',
            'step_label' => $step->label,
            'status' => str($step->status)->headline()->toString(),
            'tone' => match ($step->status) {
                'successful' => 'emerald',
                'failed' => 'rose',
                'running' => 'amber',
                default => 'slate',
            },
            'sequence' => $step->sequence,
            'command' => $step->command,
            'output' => $step->output,
            'started_at' => $step->started_at?->diffForHumans() ?? 'just now',
            'finished_at' => $step->finished_at?->diffForHumans() ?? 'in progress',
            'exit_code' => $step->exit_code,
            'url' => DeploymentResource::getUrl('view', [
                'record' => $deployment,
            ]),
        ];
    }

    protected function deploymentUrl(?Deployment $deployment): ?string
    {
        if (! $deployment) {
            return null;
        }

        return DeploymentResource::getUrl('view', [
            'record' => $deployment,
        ]);
    }
}
