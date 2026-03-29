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
}
