<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages;

use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\PrivacyPolicyVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrivacyPolicyVersion extends CreateRecord
{
    protected static string $resource = PrivacyPolicyVersionResource::class;
}
