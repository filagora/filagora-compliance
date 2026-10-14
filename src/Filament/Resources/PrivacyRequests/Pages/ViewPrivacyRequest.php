<?php

declare(strict_types=1);

namespace Filagora\Compliance\Filament\Resources\PrivacyRequests\Pages;

use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Exceptions\PrivacyRequestRefused;
use Filagora\Compliance\Filament\Resources\PrivacyOperationAudits\PrivacyOperationAuditResource;
use Filagora\Compliance\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Services\PersonalDataExport;
use Filagora\Compliance\Services\PrivacyRequests;
use Filagora\Compliance\Support\Abilities;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One privacy request, with everything staff can do to it. Every action goes through the
 * `PrivacyRequests` service, so it is audited and the person is told, and a refusal (the request
 * is on hold, it is not that type of request) is shown instead of failing.
 */
class ViewPrivacyRequest extends ViewRecord
{
    protected static string $resource = PrivacyRequestResource::class;

    private function request(): PrivacyRequest
    {
        $record = $this->getRecord();

        return $record instanceof PrivacyRequest ? $record : throw new \LogicException('Not a privacy request.');
    }

    private static function t(string $key): string
    {
        return __("filagora-compliance::ui.requests.{$key}");
    }

    /**
     * @param  callable(PrivacyRequests, PrivacyRequest): mixed  $do
     */
    private function run(callable $do, string $done): void
    {
        try {
            $do(app(PrivacyRequests::class), $this->request());
        } catch (PrivacyRequestRefused $e) {
            Notification::make()->danger()->title(self::t('notifications.refused'))->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(self::t("notifications.{$done}"))->send();
        $this->record->refresh();
        $this->refreshFormData(array_keys($this->record->getAttributes()));
    }

    private function canManage(): bool
    {
        return Gate::allows(Abilities::MANAGE_REQUESTS);
    }

    protected function getHeaderActions(): array
    {
        $actor = fn () => auth()->user();
        $record = fn (): PrivacyRequest => $this->request();

        return [
            Action::make('set_status')->label(self::t('actions.set_status'))->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->fillForm(fn (): array => ['status' => $record()->status->value])
                ->schema([Select::make('status')->label(self::t('actions.status'))->options(PrivacyRequestStatus::options())->required()])
                ->action(fn (array $data) => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->changeStatus($r, PrivacyRequestStatus::from($data['status']), $actor()), 'status_changed')),

            Action::make('hold')->label(self::t('actions.hold'))->icon('heroicon-o-lock-closed')->color('danger')
                ->visible(fn (): bool => $this->canManage() && ! $record()->is_on_hold)
                ->schema([Textarea::make('reason')->label(self::t('actions.hold_reason'))->required()->maxLength(5000)->rows(3)])
                ->action(fn (array $data) => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->hold($r, (string) $data['reason'], $actor()), 'held')),

            Action::make('release')->label(self::t('actions.release'))->icon('heroicon-o-lock-open')->color('gray')
                ->visible(fn (): bool => $this->canManage() && $record()->is_on_hold)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->release($r, $actor()), 'released')),

            Action::make('reply')->label(self::t('actions.reply'))->icon('heroicon-o-envelope')->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->schema([Textarea::make('message')->label(self::t('actions.message'))->required()->maxLength(5000)->rows(6)])
                ->action(fn (array $data) => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->reply($r, (string) $data['message'], $actor()), 'replied')),

            Action::make('add_note')->label(self::t('actions.add_note'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->schema([Textarea::make('note')->label(self::t('actions.note'))->required()->maxLength(5000)->rows(4)])
                ->action(fn (array $data) => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->addNote($r, (string) $data['note']), 'noted')),

            Action::make('export')->label(self::t('actions.export'))->icon('heroicon-o-arrow-down-tray')->color('primary')
                ->visible(fn (): bool => $this->canManage() && $record()->type === PrivacyRequestType::EXPORT && $record()->status !== PrivacyRequestStatus::FULFILLED)
                ->requiresConfirmation()
                ->modalHeading(self::t('actions.export_heading'))
                ->modalDescription(self::t('actions.export_description'))
                ->action(fn () => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->fulfilExport($r, $actor()), 'exported')),

            Action::make('erase')->label(self::t('actions.erase'))->icon('heroicon-o-trash')->color('danger')
                ->visible(fn (): bool => Gate::allows(Abilities::ERASE) && $record()->type === PrivacyRequestType::ERASURE && $record()->status !== PrivacyRequestStatus::FULFILLED)
                ->requiresConfirmation()
                ->modalHeading(self::t('actions.erase_heading'))
                ->modalDescription(self::t('actions.erase_description'))
                ->action(fn () => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->fulfilErasure($r, $actor()), 'erased')),

            Action::make('reject')->label(self::t('actions.reject'))->icon('heroicon-o-x-circle')->color('gray')
                ->visible(fn (): bool => $this->canManage() && $record()->status->isOpen())
                ->schema([Textarea::make('note')->label(self::t('actions.reject_note'))->maxLength(5000)->rows(3)])
                ->action(fn (array $data) => $this->run(fn (PrivacyRequests $s, PrivacyRequest $r) => $s->reject($r, $data['note'] ?? null, $actor()), 'rejected')),

            Action::make('download')->label(self::t('actions.download'))->icon('heroicon-o-document-arrow-down')->color('gray')
                ->visible(fn (): bool => $this->canManage() && filled($record()->export_path))
                ->action(function (): ?StreamedResponse {
                    $path = (string) $this->request()->export_path;
                    $disk = Storage::disk(PersonalDataExport::disk());

                    if (! $disk->exists($path)) {
                        Notification::make()->warning()->title(self::t('notifications.missing_file'))->send();

                        return null;
                    }

                    return $disk->download($path);
                }),

            Action::make('audits')->label(self::t('actions.audits'))->icon('heroicon-o-clipboard-document-list')->color('gray')
                ->visible(fn (): bool => Gate::allows(Abilities::AUDIT))
                ->url(fn (): string => PrivacyOperationAuditResource::getUrl('index', ['filters' => ['request' => ['privacy_request_id' => $record()->id]]])),
        ];
    }
}
