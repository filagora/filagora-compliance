<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A published version of the privacy policy. The active one is the latest active by publish date.
 *
 * @property int $id
 * @property string $version
 * @property string $label
 * @property string|null $summary
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PrivacyPolicyVersion extends Model
{
    protected $fillable = [
        'version',
        'label',
        'summary',
        'is_active',
        'published_at',
    ];

    public function getTable(): string
    {
        return Tables::name('privacy_policy_versions');
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
    public function acceptances(): HasMany
    {
        return $this->hasMany(Models::class('user_privacy_policy_acceptance'), 'privacy_policy_version_id');
    }
}
