<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A group of cookies a visitor consents to together ("Analytics"). A required group is always on.
 *
 * @property int $id
 * @property string $group_key
 * @property string $label
 * @property string|null $description
 * @property bool $is_required
 * @property bool $default_enabled
 * @property bool $is_enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CookieConsentGroup extends Model
{
    protected $fillable = [
        'group_key',
        'label',
        'description',
        'is_required',
        'default_enabled',
        'is_enabled',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            $group->group_key = Str::of((string) $group->group_key)
                ->lower()
                ->replaceMatches('/[^a-z0-9_]+/', '_')
                ->trim('_')
                ->value();
            $group->label = trim((string) $group->label);
            $group->description = $group->description !== null ? trim((string) $group->description) : null;

            if ($group->is_required) {
                $group->default_enabled = true;
            }
        });
    }

    public function getTable(): string
    {
        return Tables::name('cookie_consent_groups');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'default_enabled' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function cookies(): HasMany
    {
        return $this->hasMany(Models::class('cookie_consent_cookie'), 'group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function __toString(): string
    {
        return (string) ($this->label ?: $this->group_key ?: 'Cookie group #'.$this->id);
    }
}
