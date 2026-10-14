<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages;

use Filagora\Compliance\Filament\Resources\CookieConsentGroups\CookieConsentGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCookieConsentGroup extends EditRecord
{
    protected static string $resource = CookieConsentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
