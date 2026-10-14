<?php

declare(strict_types=1);

namespace Filagora\Compliance\Enums;

/**
 * Where a privacy request is in its handling. Every value ever stored is kept, so existing data loads.
 */
enum PrivacyRequestStatus: string
{
    case RECEIVED = 'received';
    case IN_REVIEW = 'in_review';
    case FULFILLED = 'fulfilled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::IN_REVIEW => 'In review',
            self::FULFILLED => 'Fulfilled',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * A request that is still open: not yet fulfilled or rejected.
     */
    public function isOpen(): bool
    {
        return $this === self::RECEIVED || $this === self::IN_REVIEW;
    }

    /**
     * @return array<string, string> value => label, for selects and filters.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
