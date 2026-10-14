<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\CookieConsentGroups\RelationManagers;

use Filagora\Compliance\Support\Abilities;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The cookies listed under a consent group.
 */
class CookiesRelationManager extends RelationManager
{
    protected static string $relationship = 'cookies';

    protected static ?string $title = 'Cookies';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows(Abilities::COOKIE_POLICIES);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sort_order')->numeric()->required()->default(0),
            TextInput::make('cookie_name')->required()->maxLength(128)->unique(ignoreRecord: true),
            TextInput::make('label')->required()->maxLength(160),
            TextInput::make('provider')->maxLength(255),
            TextInput::make('retention_period')->maxLength(80),
            Textarea::make('description')->rows(3)->columnSpanFull(),
            Toggle::make('is_enabled')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->numeric()->sortable(),
                TextColumn::make('cookie_name')->searchable(),
                TextColumn::make('label'),
                TextColumn::make('provider')->placeholder('-'),
                TextColumn::make('retention_period')->placeholder('-'),
                IconColumn::make('is_enabled')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
