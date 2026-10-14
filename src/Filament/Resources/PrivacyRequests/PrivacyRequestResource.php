<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyRequests;

use BackedEnum;
use Filagora\Compliance\Filament\Concerns\GatedByMap;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Schemas\PrivacyRequestInfolist;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\Tables\PrivacyRequestsTable;
use Filagora\Compliance\Support\Abilities;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Privacy requests for staff: browse, open one, and act on it (see `ViewPrivacyRequest`). Seeing
 * needs the view ability; creating is never offered (people make requests); actions have their own.
 */
class PrivacyRequestResource extends Resource
{
    use GatedByMap;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 10;

    protected static function abilities(): array
    {
        return ['default' => null, 'read' => Abilities::VIEW_REQUESTS, 'create' => null, 'delete' => Abilities::ERASE];
    }

    public static function getModel(): string
    {
        return Models::class('privacy_request');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('compliance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-compliance::ui.requests.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-compliance::ui.requests.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-compliance::ui.requests.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PrivacyRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrivacyRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrivacyRequests::route('/'),
            'view' => ViewPrivacyRequest::route('/{record}'),
        ];
    }
}
