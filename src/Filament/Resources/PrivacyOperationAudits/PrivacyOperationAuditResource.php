<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyOperationAudits;

use BackedEnum;
use Filagora\Compliance\Filament\Concerns\GatedByMap;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\Pages\ListPrivacyOperationAudits;
use Filagora\Compliance\Support\Abilities;
use Filagora\Compliance\Support\Models;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Core\Support\UserModel;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The record of what was done to privacy requests. Read-only, for those with the audit ability.
 */
class PrivacyOperationAuditResource extends Resource
{
    use GatedByMap;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 50;

    protected static function abilities(): array
    {
        return ['default' => null, 'read' => Abilities::AUDIT];
    }

    public static function getModel(): string
    {
        return Models::class('privacy_operation_audit');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('compliance');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-compliance::ui.audits.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-compliance::ui.audits.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-compliance::ui.audits.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        $title = UserModel::titleAttribute();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('privacy_request_id')->label(__('filagora-compliance::ui.audits.request_id'))->sortable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('requestUser.'.$title)->label(__('filagora-compliance::ui.audits.person')),
                TextColumn::make('actorUser.'.$title)->label(__('filagora-compliance::ui.audits.actor'))->placeholder('-'),
                TextColumn::make('meta')->formatStateUsing(fn ($state): string => is_array($state) ? (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '')->limit(80)->toggleable(),
            ])
            ->filters([
                Filter::make('request')
                    ->schema([TextInput::make('privacy_request_id')->label(__('filagora-compliance::ui.audits.request_id'))->numeric()])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['privacy_request_id'] ?? null) ? $query->where('privacy_request_id', (int) $data['privacy_request_id']) : $query),
                SelectFilter::make('action')->options(fn (): array => Models::class('privacy_operation_audit')::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPrivacyOperationAudits::route('/')];
    }
}
