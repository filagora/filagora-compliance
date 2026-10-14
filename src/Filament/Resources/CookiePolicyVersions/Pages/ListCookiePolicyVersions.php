<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCookiePolicyVersions extends ListRecords
{
    protected static string $resource = CookiePolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
