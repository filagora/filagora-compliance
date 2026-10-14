<?php

declare(strict_types=1);

use Filagora\Compliance\FilagoraCompliancePlugin;
use Filagora\Core\Modules\ModuleRegistry;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;

it('registers on a panel', function () {
    $panel = Panel::make()->id('admin')->plugin(FilagoraCompliancePlugin::make());

    expect($panel->hasPlugin('filagora-compliance'))->toBeTrue();
});

it('registers itself as a module that needs only core', function () {
    expect(app(ModuleRegistry::class)->get('compliance')->requires)->toBe(['core']);
});

it('defines the three screen abilities', function () {
    foreach (['privacy_requests', 'policies', 'audit'] as $ability) {
        expect(Gate::has("filagora.compliance.{$ability}"))->toBeTrue();
    }
});
