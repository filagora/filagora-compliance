<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages;

use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCookieConsentGroup extends CreateRecord
{
    protected static string $resource = CookieConsentGroupResource::class;
}
