<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSettingChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_setting_id',
        'user_id',
        'summary',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function appSetting(): BelongsTo
    {
        return $this->belongsTo(AppSetting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
