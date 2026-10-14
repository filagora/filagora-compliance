<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions;

use BackedEnum;
use Filagora\Compliance\Filament\Concerns\GatedByMap;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages\CreatePrivacyPolicyVersion;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages\EditPrivacyPolicyVersion;
use Filagora\Compliance\Filament\Resources\PrivacyPolicyVersions\Pages\ListPrivacyPolicyVersions;
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
 * Privacy policy versions: each version users are asked to consent to or accept. Users are asked to accept the latest active one.
 */
class PrivacyPolicyVersionResource extends Resource
{
    use GatedByMap;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 45;

    protected static function abilities(): array
    {
        return ['default' => Abilities::PRIVACY_POLICIES];
    }

    public static function getModel(): string
    {
        return Models::class('privacy_policy_version');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('compliance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-compliance::ui.privacy_versions.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-compliance::ui.privacy_versions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-compliance::ui.privacy_versions.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version')->required()->maxLength(40)->unique(ignoreRecord: true),
            TextInput::make('label')->required()->maxLength(160),
            Textarea::make('summary')->rows(4)->columnSpanFull(),
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
            'index' => ListPrivacyPolicyVersions::route('/'),
            'create' => CreatePrivacyPolicyVersion::route('/create'),
            'edit' => EditPrivacyPolicyVersion::route('/{record}/edit'),
        ];
    }
}
