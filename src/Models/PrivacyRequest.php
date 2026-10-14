<?php

declare(strict_types=1);

namespace Filagora\Compliance\Models;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person's request about their data (export, erasure and so on) and where staff are with it.
 * A request on hold is never pruned.
 *
 * @property int $id
 * @property int $user_id
 * @property PrivacyRequestType $type
 * @property PrivacyRequestStatus $status
 * @property string|null $request_details
 * @property string|null $admin_notes
 * @property string|null $export_path
 * @property bool $is_on_hold
 * @property string|null $hold_reason
 * @property Carbon|null $held_at
 * @property Carbon|null $requested_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $rejected_at
 * @property int|null $reviewer_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PrivacyRequest extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'request_details',
        'admin_notes',
        'export_path',
        'is_on_hold',
        'hold_reason',
        'held_at',
        'requested_at',
        'reviewed_at',
        'fulfilled_at',
        'rejected_at',
        'reviewer_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if ($request->requested_at === null) {
                $request->requested_at = now();
            }
        });
    }

    public function getTable(): string
    {
        return Tables::name('privacy_requests');
    }

    protected function casts(): array
    {
        return [
            'type' => PrivacyRequestType::class,
            'status' => PrivacyRequestStatus::class,
            'is_on_hold' => 'boolean',
            'held_at' => 'datetime',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'rejected_at' => 'datetime',
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'reviewer_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function operationAudits(): HasMany
    {
        return $this->hasMany(Models::class('privacy_operation_audit'), 'privacy_request_id');
    }

    /**
     * Staff replies sent to the requester, recorded as `reply_sent` operation audits with the
     * message in `meta.message`.
     *
     * @return HasMany<Model, $this>
     */
    public function replies(): HasMany
    {
        return $this->operationAudits()->where('action', 'reply_sent')->orderBy('created_at')->orderBy('id');
    }
}
