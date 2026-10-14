<?php

declare(strict_types=1);

namespace Filagora\Compliance\Listeners;

use Filagora\Cms\Events\CmsPagePublished;
use Filagora\Compliance\Services\CookiePolicyVersions;
use Filagora\Core\Settings\SettingsRegistry;

/**
 * When the page the setting `cookie_policy_page` names is published in the CMS, a new cookie
 * policy version is created, so every user is asked for consent under the new wording. The
 * setting is a route beginning with `/` (matched against the page's custom route) or a page
 * slug; empty means no page is watched. Needs `filagora/filagora-cms`; without it nothing is
 * registered.
 */
final class VersionCookiePolicyOnPagePublished
{
    public const SETTING = 'cookie_policy_page';

    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly CookiePolicyVersions $versions,
    ) {}

    public function handle(CmsPagePublished $event): void
    {
        $reference = trim($this->settings->string(self::SETTING));

        if ($reference === '' || ! $this->matches($event->page->custom_route, $event->page->slug, $reference)) {
            return;
        }

        $title = trim((string) $event->page->title);

        $this->versions->createNext(
            'Cookie policy updated',
            'Created when the cookie policy page was published in the CMS.'.($title !== '' ? " Page title: {$title}." : ''),
        );
    }

    private function matches(?string $customRoute, ?string $slug, string $reference): bool
    {
        if (str_starts_with($reference, '/')) {
            return trim((string) $customRoute) === $reference;
        }

        return trim((string) $slug) === $reference;
    }
}
