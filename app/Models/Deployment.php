<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exit_code' => 'integer',
        ];
    }

    public function isStaleFailure(int $days = 30): bool
    {
        return $this->status === 'failed'
            && filled($this->finished_at)
            && $this->finished_at->lt(now()->subDays($days));
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
}
