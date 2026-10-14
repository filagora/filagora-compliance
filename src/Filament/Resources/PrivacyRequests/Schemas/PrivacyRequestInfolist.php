<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyRequests\Schemas;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Core\Support\UserModel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PrivacyRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $t = static fn (string $key): string => __("filagora-compliance::ui.requests.infolist.{$key}");

        return $schema->components([
            Section::make($t('request'))->columns(3)->schema([
                TextEntry::make('id'),
                TextEntry::make('user.'.UserModel::titleAttribute())->label(__('filagora-compliance::ui.requests.table.user')),
                TextEntry::make('type')->badge()->formatStateUsing(fn ($state): string => $state instanceof PrivacyRequestType ? $state->label() : (string) $state),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state): string => $state instanceof PrivacyRequestStatus ? $state->label() : (string) $state),
                TextEntry::make('requested_at')->dateTime(),
                TextEntry::make('reviewed_at')->dateTime()->placeholder('-'),
                TextEntry::make('fulfilled_at')->dateTime()->placeholder('-'),
                TextEntry::make('rejected_at')->dateTime()->placeholder('-'),
                TextEntry::make('reviewer.'.UserModel::titleAttribute())->label('Reviewer')->placeholder('-'),
                TextEntry::make('request_details')->label($t('request_details'))->columnSpanFull()->placeholder('-'),
            ]),
            Section::make($t('handling'))->columns(3)->schema([
                TextEntry::make('is_on_hold')->label(__('filagora-compliance::ui.requests.table.legal_hold'))->badge()
                    ->formatStateUsing(fn ($state): string => $state ? __('filagora-compliance::ui.requests.table.on_hold') : __('filagora-compliance::ui.requests.table.no'))
                    ->color(fn ($state): string => $state ? 'danger' : 'gray'),
                TextEntry::make('held_at')->dateTime()->placeholder('-'),
                TextEntry::make('hold_reason')->label($t('hold_reason'))->placeholder('-'),
                TextEntry::make('export_path')->label($t('export_path'))->placeholder('-')->columnSpanFull(),
            ]),
            Section::make($t('notes'))->schema([
                TextEntry::make('admin_notes')->label($t('admin_notes'))->placeholder('-')->prose(),
            ]),
        ]);
    }
}
