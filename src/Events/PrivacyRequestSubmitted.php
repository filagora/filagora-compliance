<?php

declare(strict_types=1);

namespace Filagora\Compliance\Events;

use Filagora\Compliance\Models\PrivacyRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A person made a privacy request. By default the requester and the DSAR address are emailed.
 */
final class PrivacyRequestSubmitted
{
    use Dispatchable;

    public function __construct(public readonly PrivacyRequest $request) {}
}
