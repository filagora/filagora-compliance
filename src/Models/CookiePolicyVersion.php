<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A published version of the cookie policy. Only one is active: saving an active version
 * deactivates the others, and visitors whose consent was given under an older one are asked again.
 *
 * @property int $id
 * @property string $version
 * @property string $label
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CookiePolicyVersion extends Model
{
    protected $fillable = [
        'version',
        'label',
        'notes',
        'is_active',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $version): void {
            if ($version->is_active && $version->published_at === null) {
                $version->published_at = now();
            }
        });

        static::saved(static function (self $version): void {
            if (! $version->is_active) {
                return;
            }

            DB::table($version->getTable())
                ->where('id', '!=', $version->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        });
    }

    public function getTable(): string
    {
        return Tables::name('cookie_policy_versions');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function userConsents(): HasMany
    {
        return $this->hasMany(Models::class('user_cookie_consent'), 'cookie_policy_version_id');
    }
}
