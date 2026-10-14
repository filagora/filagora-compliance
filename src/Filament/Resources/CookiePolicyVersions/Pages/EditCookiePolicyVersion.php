<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\CookiePolicyVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCookiePolicyVersion extends EditRecord
{
    protected static string $resource = CookiePolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
