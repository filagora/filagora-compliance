<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Models\PrivacyOperationAudit;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Support\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the operation audit: what was done to a privacy request, by whom, and when.
 */
class PrivacyAudit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(PrivacyRequest $request, string $action, ?Model $actor = null, array $meta = []): PrivacyOperationAudit
    {
        /** @var PrivacyOperationAudit */
        return Models::class('privacy_operation_audit')::query()->create([
            'privacy_request_id' => $request->id,
            'request_user_id' => $request->user_id,
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
