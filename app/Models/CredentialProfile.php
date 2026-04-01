<?php

namespace App\Models;

use App\Casts\EncryptedJsonOrPlain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CredentialProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'settings',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => EncryptedJsonOrPlain::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'ssh' => 'SSH',
            'cpanel' => 'cPanel',
            'github' => 'GitHub',
            'dns' => 'DNS',
            'webhook' => 'Webhook',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeOptions()[$this->type] ?? ucwords(str_replace(['_', '-'], ' ', (string) $this->type));
    }

    public function getTypeBadgeColorAttribute(): string
    {
        return match ($this->type) {
            'ssh' => 'primary',
            'cpanel' => 'success',
            'github' => 'info',
            'dns' => 'warning',
            'webhook' => 'gray',
            default => 'slate',
        };
    }

    public function getSettingsSummaryAttribute(): string
    {
        $settings = (array) ($this->settings ?? []);

        if ($settings === []) {
            return 'No settings stored yet.';
        }

        $keys = collect(array_keys($settings))
            ->map(fn (string $key): string => str_replace('_', ' ', $key))
            ->take(4)
            ->values()
            ->all();

        return implode(', ', $keys).(count($settings) > 4 ? '...' : '');
    }

    /**
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @return array<int, array{id: int, name: string, type: string}>
     */
    public static function optionsForType(string $type): array
    {
        return static::query()
            ->ofType($type)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (self $profile): array => [
                'id' => $profile->id,
                'name' => $profile->name,
                'type' => $profile->type_label,
            ])
            ->all();
    }
}
