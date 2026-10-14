<?php

declare(strict_types=1);

use Filagora\Compliance\Tests\Support\HostAccess;
use Filagora\Compliance\Tests\Support\TestUser;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Illuminate\Support\Facades\Gate;

beforeEach(fn () => HostAccess::$granted = []);

it('runs with no entitlements package loaded, needing only core', function () {
    $modules = app(ModuleRegistry::class);

    expect($modules->get('compliance')->requires)->toBe(['core'])
        ->and($modules->has('entitlements'))->toBeFalse()
        ->and($modules->missingRequirements())->toBe([])
        ->and(app()->bound(EntitlementRegistry::class))->toBeFalse();
});

it('follows the host\'s own checker for the three screen abilities', function () {
    $user = TestUser::make('Host');
    HostAccess::$granted[$user->id] = ['filagora.compliance.policies'];

    expect(Gate::forUser($user)->allows('filagora.compliance.policies'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('filagora.compliance.privacy_requests'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('filagora.compliance.audit'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('filagora.compliance.policies'))->toBeFalse();
});
