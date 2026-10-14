<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One signed-in user's cookie choices and the policy version they were given under. The IP and
 * user agent are kept only as hashes.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $cookie_policy_version_id
 * @property array<string, mixed> $consent
 * @property string|null $ip_hash
 * @property string|null $user_agent_hash
 * @property Carbon $consented_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserCookieConsent extends Model
{
    protected $fillable = [
        'user_id',
        'cookie_policy_version_id',
        'consent',
        'ip_hash',
        'user_agent_hash',
        'consented_at',
    ];

    public function getTable(): string
    {
        return Tables::name('user_cookie_consents');
    }

    protected function casts(): array
    {
        return [
            'consent' => 'array',
            'consented_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(Models::class('cookie_policy_version'), 'cookie_policy_version_id');
    }
}
