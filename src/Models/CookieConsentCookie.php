<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cookie the site sets, listed under a consent group.
 *
 * @property int $id
 * @property int $group_id
 * @property string $cookie_name
 * @property string $label
 * @property string|null $description
 * @property string|null $provider
 * @property string|null $retention_period
 * @property bool $is_enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CookieConsentCookie extends Model
{
    protected $fillable = [
        'group_id',
        'cookie_name',
        'label',
        'description',
        'provider',
        'retention_period',
        'is_enabled',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $cookie): void {
            $cookie->cookie_name = trim((string) $cookie->cookie_name);
            $cookie->label = trim((string) $cookie->label);
            $cookie->description = $cookie->description !== null ? trim((string) $cookie->description) : null;
            $cookie->provider = $cookie->provider !== null ? trim((string) $cookie->provider) : null;
            $cookie->retention_period = $cookie->retention_period !== null ? trim((string) $cookie->retention_period) : null;
        });
    }

    public function getTable(): string
    {
        return Tables::name('cookie_consent_cookies');
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Models::class('cookie_consent_group'), 'group_id');
    }

    public function __toString(): string
    {
        return (string) ($this->label ?: $this->cookie_name ?: 'Cookie #'.$this->id);
    }
}
