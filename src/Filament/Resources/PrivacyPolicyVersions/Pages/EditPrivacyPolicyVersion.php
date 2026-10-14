<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrivacyPolicyVersion extends EditRecord
{
    protected static string $resource = PrivacyPolicyVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
