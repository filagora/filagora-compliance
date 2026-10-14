# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- **Privacy request lifecycle** (`PrivacyRequests`): `submit()` (refuses with a reason when the workflow is off, the type is unknown or not offered, no details are given, the person was already erased, or they have an open request of that type), `changeStatus()` (keeps dates and reviewer consistent, announces the change, audits a hand-fulfilled request), `hold()` / `release()`, `reject()`, `reply()`, `fulfilExport()` and `fulfilErasure()`. Everything staff do is written to the operation audit (`PrivacyAudit`). A request on legal hold cannot be fulfilled (lara-forum only stopped exports; erasure is stopped too).
- **Export and erasure without knowing the other packages**: `PersonalDataExport` writes one JSON file with a section for the user and one for every registered `PrivacyContributor`; `PersonalDataErasure` has every contributor erase, then the `UserAnonymizer` (default `ConfiguredColumnsAnonymizer`, set under `anonymize.attributes`) replaces the identity, in one transaction. This package contributes its own section (`ComplianceDataContributor`): export of cookie choice, acceptances and requests; erasure of the cookie choice.
- Events `PrivacyRequestSubmitted`, `PrivacyRequestStatusChanged`, `PrivacyReplySent` and default mail (requester and `dsar_request_email`) that can be switched off with `mail.enabled`; links in the mail are named routes under `routes` and are left out when not set.

- Access goes through core's `AccessChecker`, so `filagora-entitlements` is optional (a suggestion, not a requirement): the three abilities come from core's `GateAbilities`, and the module requires only `core`. Requires `filagora/filagora-core` `^0.2.1`.
- `CookieConsent` service: enabled groups with their cookies, the active policy version, a user's stored choice, `normalize()` (required groups forced on; a cookie's own choice, then its group's, then the default), `save()` (one row per user, hashed address and user agent) and `requiresReconsent()`. Setting `enable_cookie_consent_workflow`.
- `PolicyAcceptance` service: active version (latest published, cached by id for 60 seconds), `hasAcknowledgedActivePolicy()`, `stateFor()`, `accept()`. Setting `enforce_gdpr`.
- `CookiePolicyVersions` (day-numbered versions) and, when `filagora/filagora-cms` is installed, a listener that creates a cookie policy version when the page named by the setting `cookie_policy_page` is published.
- Settings `dsar_request_email`, `cookie_policy_page` and `privacy_page` declared with the site's defaults.

- The eight tables (`cookie_consent_groups`, `cookie_consent_cookies`, `cookie_policy_versions`, `user_cookie_consents`, `privacy_policy_versions`, `user_privacy_policy_acceptances`, `privacy_requests`, `privacy_operation_audits`) in one migration, matching the site the package was extracted from index for index; a schema snapshot so `filagora:adopt` accepts an existing site's tables.
- Models (swappable through `filagora-compliance.models`) and the enums `PrivacyRequestStatus` and `PrivacyRequestType`.
- Abilities `filagora.compliance.privacy_requests`, `.policies` and `.audit` (config `gates`), declared as entitlements.
