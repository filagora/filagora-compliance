<?php

declare(strict_types=1);

namespace Filagora\Compliance\Events;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A request moved from one status to another. By default the requester is emailed.
 */
final class PrivacyRequestStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly PrivacyRequest $request,
        public readonly PrivacyRequestStatus $from,
        public readonly PrivacyRequestStatus $to,
    ) {}
}
