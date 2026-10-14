<?php

declare(strict_types=1);

namespace Filagora\Compliance\Tests\Support;

use Filagora\Compliance\FilagoraCompliancePlugin;
use Filagora\Core\FilagoraCorePlugin;
use Filagora\Entitlements\FilagoraEntitlementsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilagoraCorePlugin::make())
            ->plugin(FilagoraEntitlementsPlugin::make())
            ->plugin(FilagoraCompliancePlugin::make());
    }
}
