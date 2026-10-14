<?php

declare(strict_types=1);

namespace Filagora\Compliance\Enums;

/**
 * What a person asks for. `access` is kept only so historical rows load; it is not offered on
 * new submissions (see `selectable()`).
 */
enum PrivacyRequestType: string
{
    case ACCESS = 'access';
    case EXPORT = 'export';
    case RECTIFICATION = 'rectification';
    case ERASURE = 'erasure';
    case RESTRICTION = 'restriction';
    case OBJECTION = 'objection';

    public function label(): string
    {
        return match ($this) {
            self::ACCESS => 'Access',
            self::EXPORT => 'Data export',
            self::RECTIFICATION => 'Rectification',
            self::ERASURE => 'Erasure',
            self::RESTRICTION => 'Restriction',
            self::OBJECTION => 'Objection',
        };
    }

    /**
     * @return array<string, string> value => label, every type, for staff screens.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The types a person may choose when submitting a request.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => $case !== self::ACCESS));
    }

    /**
     * @return array<string, string> value => label, for the submission form.
     */
    public static function selectableOptions(): array
    {
        $options = [];

        foreach (self::selectable() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
