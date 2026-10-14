<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyPolicyVersions extends ListRecords
{
    protected static string $resource = PrivacyPolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
