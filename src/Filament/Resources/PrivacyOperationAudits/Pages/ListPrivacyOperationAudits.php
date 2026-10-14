<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\Pages;

use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\PrivacyOperationAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyOperationAudits extends ListRecords
{
    protected static string $resource = PrivacyOperationAuditResource::class;
}
