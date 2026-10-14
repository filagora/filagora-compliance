<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filagora\Cms\Events\CmsPagePublished;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Models\CmsPageVersion;
use Filagora\Compliance\Models\CookiePolicyVersion;
use Filagora\Compliance\Services\CookiePolicyVersions;
use Filagora\Core\Settings\SettingsRegistry;

function publishedPage(array $attributes): CmsPagePublished
{
    return new CmsPagePublished(new CmsPage($attributes), new CmsPageVersion, null);
}

it('numbers versions by day, counting up within the day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00:00'));
    $service = app(CookiePolicyVersions::class);

    $first = $service->createNext('First');
    $second = $service->createNext('Second', 'A note');
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00:00'));
    $third = $service->createNext('Third');

    expect([$first->version, $second->version, $third->version])->toBe(['2026.10.14.1', '2026.10.14.2', '2026.10.15.1'])
        ->and($second->notes)->toBe('A note')
        ->and(CookiePolicyVersion::query()->where('is_active', true)->pluck('version')->all())->toBe(['2026.10.15.1']);
});

it('goes on from the highest number of the day, not the count', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00:00'));
    CookiePolicyVersion::create(['version' => '2026.10.14.1', 'label' => 'One', 'is_active' => false]);
    CookiePolicyVersion::create(['version' => '2026.10.14.5', 'label' => 'Five', 'is_active' => false]);

    expect(app(CookiePolicyVersions::class)->nextVersionString())->toBe('2026.10.14.6');
});

it('counts past nine, not just by string order', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:00:00'));
    $service = app(CookiePolicyVersions::class);

    foreach (range(1, 10) as $i) {
        $service->createNext("v{$i}");
    }

    expect($service->nextVersionString())->toBe('2026.10.14.11');
});

it('defines the settings the services read, with the site\'s defaults', function () {
    $settings = app(SettingsRegistry::class);

    expect($settings->boolean('enable_cookie_consent_workflow'))->toBeTrue()
        ->and($settings->boolean('enforce_gdpr'))->toBeTrue()
        ->and($settings->string('dsar_request_email'))->toBe('')
        ->and($settings->string('cookie_policy_page'))->toBe('/cookie-policy')
        ->and($settings->string('privacy_page'))->toBe('/privacy');
});

it('creates a version when the page the setting names is published, by route or by slug', function () {
    $settings = app(SettingsRegistry::class);

    event(publishedPage(['slug' => 'cookies', 'custom_route' => '/cookie-policy', 'title' => 'Cookie policy']));
    expect(CookiePolicyVersion::count())->toBe(1)
        ->and(CookiePolicyVersion::first()->notes)->toContain('Page title: Cookie policy.');

    $settings->set('cookie_policy_page', 'our-cookies');
    event(publishedPage(['slug' => 'our-cookies', 'custom_route' => null, 'title' => '']));

    expect(CookiePolicyVersion::count())->toBe(2)
        ->and(CookiePolicyVersion::query()->where('is_active', true)->count())->toBe(1);
});

it('ignores other pages, and every page when the setting is empty', function () {
    $settings = app(SettingsRegistry::class);

    event(publishedPage(['slug' => 'about', 'custom_route' => '/about']));
    event(publishedPage(['slug' => 'cookie-policy', 'custom_route' => null]));
    expect(CookiePolicyVersion::count())->toBe(0);

    $settings->set('cookie_policy_page', '');
    event(publishedPage(['slug' => '', 'custom_route' => '']));
    expect(CookiePolicyVersion::count())->toBe(0);
});
