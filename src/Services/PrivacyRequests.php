<?php

declare(strict_types=1);

namespace Filagora\Compliance\Services;

use Filagora\Compliance\Enums\PrivacyRequestRefusal;
use Filagora\Compliance\Enums\PrivacyRequestStatus;
use Filagora\Compliance\Enums\PrivacyRequestType;
use Filagora\Compliance\Events\PrivacyReplySent;
use Filagora\Compliance\Events\PrivacyRequestStatusChanged;
use Filagora\Compliance\Events\PrivacyRequestSubmitted;
use Filagora\Compliance\Exceptions\PrivacyRequestRefused;
use Filagora\Compliance\Models\PrivacyRequest;
use Filagora\Compliance\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The life of a privacy request: a person submits it, staff review it, put it on legal hold,
 * reply, fulfil it (an export file, or erasure) or reject it. Everything staff do is written to the
 * operation audit, and the person is told by event-driven mail (see the `mail` config).
 */
class PrivacyRequests
{
    public const NOTE_MAX = 5000;

    public function __construct(
        private readonly PolicyAcceptance $policy,
        private readonly PrivacyAudit $audit,
        private readonly PersonalDataExport $export,
        private readonly PersonalDataErasure $erasure,
    ) {}

    /**
     * A person asks for something. Refused (PrivacyRequestRefused) when the privacy workflow is
     * switched off, the type is not one people may ask for, no details are given, they were
     * already erased, or they have an open request of that type.
     */
    public function submit(Model $user, PrivacyRequestType|string $type, ?string $details): PrivacyRequest
    {
        if (! $this->policy->isEnforced()) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::ENFORCEMENT_OFF);
        }

        $requested = $type instanceof PrivacyRequestType ? $type : PrivacyRequestType::tryFrom($type);

        if ($requested === null || ! in_array($requested, PrivacyRequestType::selectable(), true)) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::UNKNOWN_TYPE);
        }

        $details = trim((string) $details);

        if ($details === '') {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::DETAILS_REQUIRED);
        }

        $requests = Models::class('privacy_request')::query()->where('user_id', $user->getKey());

        if ((clone $requests)->where('type', PrivacyRequestType::ERASURE->value)->where('status', PrivacyRequestStatus::FULFILLED->value)->exists()) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::ERASURE_ALREADY_FULFILLED);
        }

        if ((clone $requests)->where('type', $requested->value)->whereIn('status', [PrivacyRequestStatus::RECEIVED->value, PrivacyRequestStatus::IN_REVIEW->value])->exists()) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::OPEN_REQUEST_OF_TYPE);
        }

        /** @var PrivacyRequest $request */
        $request = Models::class('privacy_request')::query()->create([
            'user_id' => $user->getKey(),
            'type' => $requested->value,
            'status' => PrivacyRequestStatus::RECEIVED->value,
            'request_details' => mb_substr($details, 0, self::NOTE_MAX),
            'requested_at' => now(),
        ]);

        PrivacyRequestSubmitted::dispatch($request);

        return $request;
    }

    /**
     * Move a request to a status and keep its dates and reviewer consistent: anything past
     * received has a review date; fulfilled and rejected have their own date, and a request that
     * leaves those states loses it. Does nothing, and sends nothing, if the status is unchanged.
     * A request fulfilled by hand, with no audit yet, gets a fallback audit row (the fulfil
     * methods pass false: they write their own).
     */
    public function changeStatus(PrivacyRequest $request, PrivacyRequestStatus $status, ?Model $reviewer = null, bool $fallbackAudit = true): PrivacyRequest
    {
        $from = $request->status;

        $request->status = $status;
        $request->reviewer_id = $reviewer?->getKey() ?? $request->reviewer_id;

        if ($status !== PrivacyRequestStatus::RECEIVED) {
            $request->reviewed_at ??= now();
        }

        $request->fulfilled_at = $status === PrivacyRequestStatus::FULFILLED ? ($request->fulfilled_at ?? now()) : null;
        $request->rejected_at = $status === PrivacyRequestStatus::REJECTED ? ($request->rejected_at ?? now()) : null;
        $request->save();

        if ($from !== $status) {
            if ($fallbackAudit && $status === PrivacyRequestStatus::FULFILLED && ! $request->operationAudits()->exists()) {
                $this->audit->record($request, 'request_fulfilled_status_update', $reviewer, ['fulfilled_at' => $request->fulfilled_at?->toIso8601String()]);
            }

            PrivacyRequestStatusChanged::dispatch($request, $from, $status);
        }

        return $request;
    }

    /**
     * Add a line to the staff notes, dated. Nothing is sent.
     */
    public function addNote(PrivacyRequest $request, string $note): PrivacyRequest
    {
        $this->appendNote($request, trim($note));
        $request->save();

        return $request;
    }

    /**
     * Put a request on legal hold: it is not pruned, and cannot be fulfilled until released.
     */
    public function hold(PrivacyRequest $request, string $reason, ?Model $actor = null): PrivacyRequest
    {
        $request->forceFill(['is_on_hold' => true, 'hold_reason' => trim($reason), 'held_at' => now()])->save();
        $this->audit->record($request, 'hold_placed', $actor, ['reason' => trim($reason)]);

        return $request;
    }

    public function release(PrivacyRequest $request, ?Model $actor = null): PrivacyRequest
    {
        $request->forceFill(['is_on_hold' => false, 'hold_reason' => null, 'held_at' => null])->save();
        $this->audit->record($request, 'hold_released', $actor);

        return $request;
    }

    public function reject(PrivacyRequest $request, ?string $note = null, ?Model $actor = null): PrivacyRequest
    {
        if (trim((string) $note) !== '') {
            $this->appendNote($request, 'Rejected: '.trim((string) $note));
        }

        $request = $this->changeStatus($request, PrivacyRequestStatus::REJECTED, $actor, false);
        $this->audit->record($request, 'request_rejected', $actor, array_filter(['note' => trim((string) $note)]));

        return $request;
    }

    /**
     * Staff write to the person. The message is noted on the request, audited, and sent by mail.
     */
    public function reply(PrivacyRequest $request, string $message, ?Model $actor = null): PrivacyRequest
    {
        $this->requireUser($request);

        $this->appendNote($request, 'Reply sent to requester: '.$message);
        $request->save();
        $this->audit->record($request, 'reply_sent', $actor, ['message' => $message, 'sent_at' => now()->toIso8601String()]);

        PrivacyReplySent::dispatch($request, $message);

        return $request;
    }

    /**
     * Build the person's export file, mark the request fulfilled and record where the file is.
     */
    public function fulfilExport(PrivacyRequest $request, ?Model $actor = null): PrivacyRequest
    {
        $user = $this->requireFulfillable($request, PrivacyRequestType::EXPORT);

        $path = $this->export->store($user);
        $this->appendNote($request, "User export generated at {$path}.");
        $request->export_path = $path;
        $request = $this->changeStatus($request, PrivacyRequestStatus::FULFILLED, $actor, false);
        $this->audit->record($request, 'export_fulfilled', $actor, ['export_path' => $path, 'fulfilled_at' => $request->fulfilled_at?->toIso8601String()]);

        return $request;
    }

    /**
     * Erase the person (every contributor, then the anonymiser) and mark the request fulfilled.
     */
    public function fulfilErasure(PrivacyRequest $request, ?Model $actor = null): PrivacyRequest
    {
        $user = $this->requireFulfillable($request, PrivacyRequestType::ERASURE);

        DB::transaction(function () use ($request, $user, $actor): void {
            $this->erasure->erase($user);
            $this->appendNote($request, 'User account anonymized via DSAR erasure action.');
            $this->changeStatus($request, PrivacyRequestStatus::FULFILLED, $actor, false);
            $this->audit->record($request, 'erasure_fulfilled', $actor, ['fulfilled_at' => $request->fulfilled_at?->toIso8601String()]);
        });

        return $request;
    }

    private function requireUser(PrivacyRequest $request): Model
    {
        $user = $request->user;

        if (! $user instanceof Model) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::NO_USER);
        }

        return $user;
    }

    private function requireFulfillable(PrivacyRequest $request, PrivacyRequestType $type): Model
    {
        if ($request->type !== $type) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::WRONG_TYPE);
        }

        if ($request->is_on_hold) {
            throw new PrivacyRequestRefused(PrivacyRequestRefusal::ON_HOLD);
        }

        return $this->requireUser($request);
    }

    private function appendNote(PrivacyRequest $request, string $line): void
    {
        $request->admin_notes = trim(implode("\n\n", array_filter([
            (string) $request->admin_notes,
            sprintf('[%s] %s', now()->toIso8601String(), $line),
        ], static fn (string $part): bool => $part !== '')));
    }
}
