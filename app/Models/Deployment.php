<?php

namespace App\Models;

use App\Support\DeploymentCommandGuide;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'triggered_by_user_id',
        'source',
        'status',
        'branch',
        'commit_hash',
        'release_path',
        'started_at',
        'finished_at',
        'exit_code',
        'output',
        'error_message',
        'recovery_hint',
        'archive_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'archive_uploaded_at' => 'datetime',
            'exit_code' => 'integer',
        ];
    }

    public function isStaleFailure(int $days = 30): bool
    {
        return $this->status === 'failed'
            && filled($this->finished_at)
            && $this->finished_at->lt(now()->subDays($days));
    }

    public function isResumable(): bool
    {
        return $this->status === 'failed'
            && filled($this->release_path)
            && (
                filled($this->archive_uploaded_at)
                || $this->steps()->exists()
                || in_array(optional($this->site)->deploy_source, ['git', 'local'], true)
            );
    }

    /**
     * @return Builder<self>
     */
    public function scopeVisibleInAdmin(Builder $query, int $days = 30): Builder
    {
        return $query->where(function (Builder $query) use ($days): void {
            $query->where('status', '!=', 'failed')
                ->orWhere(function (Builder $query) use ($days): void {
                    $query->where('status', 'failed')
                        ->where(function (Builder $query) use ($days): void {
                            $query->whereNull('finished_at')
                                ->orWhere('finished_at', '>=', now()->subDays($days));
                        });
                });
        });
    }

    /**
     * @return Builder<self>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('site', function (Builder $query) use ($user): void {
            $query->accessibleTo($user);
        });
    }

    /**
     * @return Builder<self>
     */
    public function scopeStaleFailures(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'failed')
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', now()->subDays($days));
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DeploymentStep::class)->orderBy('sequence');
    }

    /**
     * @return array<int, array{title: string, description: string, command: string, usage: string, intro: string}>
     */
    public function getCommandGuideSnippetsAttribute(): array
    {
        return app(DeploymentCommandGuide::class)->snippetsFor($this->fresh(['site']));
    }

    public function getCommandGuideIntroAttribute(): string
    {
        return app(DeploymentCommandGuide::class)->introFor($this->fresh(['site']));
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageSnapshotAttribute(): array
    {
        $progress = $this->getStepProgressAttribute();

        return [
            'headline' => match ($this->status) {
                'successful' => 'Deployment completed successfully',
                'running' => 'Deployment is running',
                'failed' => $this->isResumable()
                    ? 'Deployment stopped, but it can be resumed'
                    : 'Deployment stopped and needs attention',
                'pending' => 'Deployment is queued and waiting to start',
                default => 'Deployment status is pending review',
            },
            'summary' => match ($this->status) {
                'successful' => filled($this->release_path)
                    ? sprintf('The site is live on %s and the release path is ready for rollback or inspection.', $this->release_path)
                    : 'The deployment finished successfully and the site is ready to use.',
                'running' => 'The deployment is still active. The terminal below will refresh until the job finishes.',
                'failed' => $this->isResumable()
                    ? 'A part of the deployment already succeeded. Fix the issue below, then use Resume deployment to continue from the next incomplete step.'
                    : 'The deployment stopped before it could finish. Review the failure summary and fix the blocking issue before trying again.',
                'pending' => 'The deployment has been queued. Once a worker picks it up, you will see the terminal start moving.',
                default => 'Review the current deployment state before taking the next action.',
            },
            'next_action' => match (true) {
                $this->status === 'running' => 'Watch live progress',
                $this->status === 'failed' && $this->isResumable() => 'Resume deployment',
                $this->status === 'failed' => 'Fix the failure and retry',
                $this->status === 'successful' => 'Open command guide',
                default => 'Wait for the worker',
            },
            'next_action_description' => match (true) {
                $this->status === 'running' => 'Keep this page open while the terminal updates. You can monitor each step as it finishes.',
                $this->status === 'failed' && $this->isResumable() => 'The uploaded archive and earlier steps are already saved. After fixing the issue, Resume deployment will continue from the next incomplete step.',
                $this->status === 'failed' => 'Check the failure summary and recovery hint, then queue a fresh retry once the underlying problem is fixed.',
                $this->status === 'successful' => 'Use the command snippets to inspect the release, restart workers, or perform follow-up tasks safely.',
                default => 'The job is queued. The page will update automatically when the worker begins.',
            },
            'tone' => match ($this->status) {
                'successful' => 'success',
                'running' => 'warning',
                'failed' => 'danger',
                'pending' => 'info',
                default => 'gray',
            },
            'badges' => [
                [
                    'label' => ucfirst((string) $this->status),
                    'color' => match ($this->status) {
                        'successful' => 'success',
                        'running' => 'warning',
                        'failed' => 'danger',
                        'pending' => 'info',
                        default => 'gray',
                    },
                ],
                [
                    'label' => ucfirst((string) $this->source),
                    'color' => 'slate',
                ],
                [
                    'label' => $this->branch ?: 'main',
                    'color' => 'primary',
                ],
                [
                    'label' => filled($this->release_path) ? 'Release ready' : 'No release path',
                    'color' => filled($this->release_path) ? 'success' : 'gray',
                ],
                [
                    'label' => $this->isResumable() ? 'Resumable' : 'Not resumable',
                    'color' => $this->isResumable() ? 'success' : 'gray',
                ],
            ],
            'checklist' => match (true) {
                $this->status === 'running' => [
                    'Keep this page open while the terminal refreshes.',
                    'Watch the active step for new output or errors.',
                    'Use the command guide if you need to inspect the release while it runs.',
                ],
                $this->status === 'failed' && $this->isResumable() => [
                    'Review the failure summary and recovery hint.',
                    'Fix the blocking issue shown in the latest step output.',
                    'Use Resume deployment so the already completed work is reused.',
                ],
                $this->status === 'failed' => [
                    'Read the failure summary and open the failed step output.',
                    'Fix the blocking issue before trying again.',
                    'Use Retry when you are ready to queue a fresh attempt.',
                ],
                $this->status === 'successful' => [
                    'Use the command guide to inspect or maintain the release.',
                    'Rollback is available if a previous release needs to be restored.',
                    'The terminal history can help confirm exactly what happened during deploy.',
                ],
                default => [
                    'Wait for the worker to pick up the deployment.',
                    'The terminal will begin showing progress when the job starts.',
                    'You can keep the page open and monitor live updates.',
                ],
            },
            'timing' => [
                'started' => filled($this->started_at) ? $this->started_at->format('Y-m-d H:i:s') : 'Not started yet',
                'finished' => filled($this->finished_at) ? $this->finished_at->format('Y-m-d H:i:s') : 'In progress',
                'elapsed' => $this->elapsedLabel(),
            ],
            'progress' => $progress,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function getStepProgressAttribute(): array
    {
        $steps = $this->relationLoaded('steps') ? $this->steps : $this->steps()->get();
        $total = $steps->count();
        $completed = $steps->where('status', 'successful')->count();
        $running = $steps->where('status', 'running')->count();
        $failed = $steps->where('status', 'failed')->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'running' => $running,
            'failed' => $failed,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'summary' => $total > 0
                ? sprintf('%d of %d steps complete', $completed, $total)
                : 'No steps recorded yet',
        ];
    }

    public function elapsedLabel(): string
    {
        if (filled($this->started_at) && filled($this->finished_at)) {
            return CarbonInterval::seconds(max(0, $this->started_at->diffInSeconds($this->finished_at)))->cascade()->forHumans(short: true);
        }

        if (filled($this->started_at)) {
            return CarbonInterval::seconds(max(0, $this->started_at->diffInSeconds(now())))->cascade()->forHumans(short: true);
        }

        return 'Not started';
    }
}
