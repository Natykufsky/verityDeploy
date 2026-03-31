<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteBackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'triggered_by_user_id',
        'source_backup_id',
        'operation',
        'status',
        'label',
        'source_release_path',
        'snapshot_path',
        'restored_release_path',
        'size_bytes',
        'checksum',
        'output',
        'error_message',
        'recovery_hint',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function sourceBackup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_backup_id');
    }
}
