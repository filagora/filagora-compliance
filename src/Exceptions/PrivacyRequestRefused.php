<?php

declare(strict_types=1);

namespace Filagora\Compliance\Exceptions;

use Filagora\Compliance\Enums\PrivacyRequestRefusal;
use RuntimeException;

final class PrivacyRequestRefused extends RuntimeException
{
    public function __construct(public readonly PrivacyRequestRefusal $reason)
    {
        parent::__construct($reason->message());
    }
}
