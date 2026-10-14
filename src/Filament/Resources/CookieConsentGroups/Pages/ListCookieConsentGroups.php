<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages;

use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCookieConsentGroups extends ListRecords
{
    protected static string $resource = CookieConsentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
