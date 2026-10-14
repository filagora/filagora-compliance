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
 * One thing done to a privacy request (an export fulfilled, a reply sent), kept as the record of
 * how the request was handled.
 *
 * @property int $id
 * @property int $privacy_request_id
 * @property int $request_user_id
 * @property int|null $actor_user_id
 * @property string $action
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PrivacyOperationAudit extends Model
{
    protected $fillable = [
        'privacy_request_id',
        'request_user_id',
        'actor_user_id',
        'action',
        'meta',
    ];

    public function getTable(): string
    {
        return Tables::name('privacy_operation_audits');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(Models::class('privacy_request'), 'privacy_request_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function requestUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'request_user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'actor_user_id');
    }
}
