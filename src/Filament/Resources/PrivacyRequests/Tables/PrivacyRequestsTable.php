<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyRequests\Tables;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Core\Support\UserModel;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrivacyRequestsTable
{
    public static function configure(Table $table): Table
    {
        $t = static fn (string $key): string => __("filagora-compliance::ui.requests.table.{$key}");

        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.'.UserModel::titleAttribute())->label($t('user'))->searchable()->sortable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state): string => $state instanceof PrivacyRequestType ? $state->label() : (string) $state),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof PrivacyRequestStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => match ($state) {
                        PrivacyRequestStatus::FULFILLED => 'success',
                        PrivacyRequestStatus::REJECTED => 'gray',
                        PrivacyRequestStatus::IN_REVIEW => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('is_on_hold')->label($t('legal_hold'))->badge()
                    ->formatStateUsing(fn ($state): string => $state ? $t('on_hold') : $t('no'))
                    ->color(fn ($state): string => $state ? 'danger' : 'gray'),
                TextColumn::make('requested_at')->dateTime()->sortable(),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('-')->sortable()->toggleable(),
                TextColumn::make('fulfilled_at')->dateTime()->placeholder('-')->sortable()->toggleable(),
            ])
            ->filters([
                Filter::make('hide_rejected')->label($t('hide_rejected'))->default()
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', PrivacyRequestStatus::REJECTED->value)),
                SelectFilter::make('type')->options(PrivacyRequestType::options()),
                SelectFilter::make('status')->options(PrivacyRequestStatus::options()),
                SelectFilter::make('is_on_hold')->label($t('legal_hold'))
                    ->options(['1' => $t('on_hold'), '0' => $t('no')])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return $value === null || $value === '' ? $query : $query->where('is_on_hold', $value === '1');
                    }),
            ])
            ->recordActions([ViewAction::make()])
            ->recordActionsPosition(RecordActionsPosition::BeforeCells);
    }
}
