<?php

declare(strict_types=1);

namespace Filagora\Compliance\Events;

use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff replied to the person who made a request. By default the reply is emailed to them.
 */
final class PrivacyReplySent
{
    use Dispatchable;

    public function __construct(
        public readonly PrivacyRequest $request,
        public readonly string $message,
    ) {}
}
