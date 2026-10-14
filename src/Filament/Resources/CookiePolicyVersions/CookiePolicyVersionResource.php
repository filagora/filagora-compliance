<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookiePolicyVersions;

use BackedEnum;
use Filagora\Compliance\Filament\Concerns\GatedByMap;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages\CreateCookiePolicyVersion;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages\EditCookiePolicyVersion;
use Filagora\Compliance\Filament\Resources\CookiePolicyVersions\Pages\ListCookiePolicyVersions;
use Filagora\Compliance\Support\Abilities;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
 * Cookie policy versions: each version users are asked to consent to or accept. Only one is active: activating a version deactivates the others, and users are asked for consent again.
 */
class CookiePolicyVersionResource extends Resource
{
    use GatedByMap;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 40;

    protected static function abilities(): array
    {
        return ['default' => Abilities::COOKIE_POLICIES];
    }

    public static function getModel(): string
    {
        return Models::class('cookie_policy_version');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('compliance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-compliance::ui.cookie_versions.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-compliance::ui.cookie_versions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-compliance::ui.cookie_versions.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version')->required()->maxLength(40)->unique(ignoreRecord: true),
            TextInput::make('label')->required()->maxLength(120),
            Textarea::make('notes')->rows(4)->columnSpanFull(),
            Toggle::make('is_active')->default(true),
            DateTimePicker::make('published_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('version')->searchable()->sortable(),
                TextColumn::make('label')->searchable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('published_at')->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCookiePolicyVersions::route('/'),
            'create' => CreateCookiePolicyVersion::route('/create'),
            'edit' => EditCookiePolicyVersion::route('/{record}/edit'),
        ];
    }
}
