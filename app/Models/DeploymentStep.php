<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'deployment_id',
        'sequence',
        'label',
        'command',
        'status',
        'output',
        'error_message',
        'started_at',
        'finished_at',
        'exit_code',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'sequence' => 'integer',
            'exit_code' => 'integer',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function getSeverityAttribute(): string
    {
        return match ($this->status) {
            'successful' => 'success',
            'running' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function getSummaryAttribute(): string
    {
        return match ($this->status) {
            'successful' => 'Completed successfully',
            'running' => 'Currently running',
            'failed' => 'Needs attention',
            default => 'Waiting for update',
        };
    }

    public function getIsExpandedAttribute(): bool
    {
        return $this->status !== 'successful';
    }
}
