<?php

declare(strict_types=1);

namespace Filagora\Compliance\Support;

/**
 * The Gate abilities of the compliance screens. Each maps to a key in `filagora-compliance.gates`,
 * which a host can point at its own keys.
 */
final class Abilities
{
    /** See privacy requests, their audits' count and the privacy KPIs. */
    public const VIEW_REQUESTS = 'filagora.compliance.privacy_requests.view';

    /** Handle privacy requests: review, hold, reply, export, reject. */
    public const MANAGE_REQUESTS = 'filagora.compliance.privacy_requests';

    /** Erase a person (fulfil an erasure request) and delete requests. */
    public const ERASE = 'filagora.compliance.erase';

    /** Manage cookie consent groups, cookies and cookie policy versions. */
    public const COOKIE_POLICIES = 'filagora.compliance.cookie_policies';

    /** Manage privacy policy versions. */
    public const PRIVACY_POLICIES = 'filagora.compliance.privacy_policies';

    /** Read the privacy operation audit. */
    public const AUDIT = 'filagora.compliance.audit';
}
