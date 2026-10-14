<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookieConsentGroups;

use BackedEnum;
use Filagora\Compliance\Filament\Concerns\GatedByMap;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\CreateCookieConsentGroup;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\EditCookieConsentGroup;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\Pages\ListCookieConsentGroups;
use Filagora\Compliance\Filament\Resources\CookieConsentGroups\RelationManagers\CookiesRelationManager;
use Filagora\Compliance\Support\Abilities;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The groups of cookies a visitor consents to (necessary, analytics, ...), with their cookies.
 */
class CookieConsentGroupResource extends Resource
{
    use GatedByMap;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 30;

    protected static function abilities(): array
    {
        return ['default' => Abilities::COOKIE_POLICIES];
    }

    public static function getModel(): string
    {
        return Models::class('cookie_consent_group');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('compliance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-compliance::ui.cookie_groups.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-compliance::ui.cookie_groups.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-compliance::ui.cookie_groups.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sort_order')->numeric()->required()->default(0),
            TextInput::make('group_key')->required()->maxLength(80)->unique(ignoreRecord: true),
            TextInput::make('label')->required()->maxLength(120),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Toggle::make('is_required')->helperText(__('filagora-compliance::ui.cookie_groups.required_help'))->live()
                ->afterStateUpdated(fn ($state, callable $set) => $state ? $set('default_enabled', true) : null),
            Toggle::make('default_enabled')->disabled(fn (callable $get): bool => (bool) $get('is_required')),
            Toggle::make('is_enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->numeric()->sortable(),
                TextColumn::make('group_key')->searchable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('cookies_count')->counts('cookies')->label(__('filagora-compliance::ui.cookie_groups.cookies')),
                IconColumn::make('is_required')->boolean(),
                IconColumn::make('default_enabled')->boolean(),
                IconColumn::make('is_enabled')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [CookiesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCookieConsentGroups::route('/'),
            'create' => CreateCookieConsentGroup::route('/create'),
            'edit' => EditCookieConsentGroup::route('/{record}/edit'),
        ];
    }
}
