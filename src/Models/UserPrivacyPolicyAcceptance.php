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
 * A user's acceptance of one version of the privacy policy. The IP and user agent are kept only
 * as hashes.
 *
 * @property int $id
 * @property int $user_id
 * @property int $privacy_policy_version_id
 * @property Carbon $accepted_at
 * @property string|null $ip_hash
 * @property string|null $user_agent_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserPrivacyPolicyAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'privacy_policy_version_id',
        'accepted_at',
        'ip_hash',
        'user_agent_hash',
    ];

    public function getTable(): string
    {
        return Tables::name('user_privacy_policy_acceptances');
    }

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
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
        return $this->belongsTo(Models::class('privacy_policy_version'), 'privacy_policy_version_id');
    }
}
