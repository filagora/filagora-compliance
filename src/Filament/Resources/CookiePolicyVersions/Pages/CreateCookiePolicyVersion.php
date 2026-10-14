<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCookiePolicyVersion extends CreateRecord
{
    protected static string $resource = CookiePolicyVersionResource::class;
}
