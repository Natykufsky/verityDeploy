<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpanelWizardRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'site_id',
        'wizard_type',
        'status',
        'steps',
        'summary',
        'error_message',
        'recovery_hint',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'successful';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function getWizardTypeLabelAttribute(): string
    {
        return match ($this->wizard_type) {
            'server_checks' => 'Server wizard',
            'site_bootstrap' => 'Site wizard',
            default => str($this->wizard_type)->headline()->toString(),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'successful' => 'success',
            'failed' => 'danger',
            'running' => 'warning',
            default => 'gray',
        };
    }

    public function getStartedAtLabelAttribute(): string
    {
        return $this->started_at?->diffForHumans() ?? 'just now';
    }
}
