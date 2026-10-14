<?php

declare(strict_types=1);

namespace Filagora\Compliance\Enums;

/**
 * Why a privacy request, or an action on one, was refused.
 */
enum PrivacyRequestRefusal: string
{
    case ENFORCEMENT_OFF = 'enforcement_off';
    case UNKNOWN_TYPE = 'unknown_type';
    case DETAILS_REQUIRED = 'details_required';
    case ERASURE_ALREADY_FULFILLED = 'erasure_already_fulfilled';
    case OPEN_REQUEST_OF_TYPE = 'open_request_of_type';
    case WRONG_TYPE = 'wrong_type';
    case ON_HOLD = 'on_hold';
    case NO_USER = 'no_user';

    public function message(): string
    {
        return __("filagora-compliance::requests.refusals.{$this->value}");
    }
}
