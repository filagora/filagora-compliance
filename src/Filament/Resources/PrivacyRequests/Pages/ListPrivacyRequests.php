<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages;

use Filagora\Compliance\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyRequests extends ListRecords
{
    protected static string $resource = PrivacyRequestResource::class;
}
