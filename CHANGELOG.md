# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- `CookieConsent` service: enabled groups with their cookies, the active policy version, a user's stored choice, `normalize()` (required groups forced on; a cookie's own choice, then its group's, then the default), `save()` (one row per user, hashed address and user agent) and `requiresReconsent()`. Setting `enable_cookie_consent_workflow`.
- `PolicyAcceptance` service: active version (latest published, cached by id for 60 seconds), `hasAcknowledgedActivePolicy()`, `stateFor()`, `accept()`. Setting `enforce_gdpr`.
- `CookiePolicyVersions` (day-numbered versions) and, when `filagora/filagora-cms` is installed, a listener that creates a cookie policy version when the page named by the setting `cookie_policy_page` is published.
- Settings `dsar_request_email`, `cookie_policy_page` and `privacy_page` declared with the site's defaults.

- The eight tables (`cookie_consent_groups`, `cookie_consent_cookies`, `cookie_policy_versions`, `user_cookie_consents`, `privacy_policy_versions`, `user_privacy_policy_acceptances`, `privacy_requests`, `privacy_operation_audits`) in one migration, matching the site the package was extracted from index for index; a schema snapshot so `filagora:adopt` accepts an existing site's tables.
- Models (swappable through `filagora-compliance.models`) and the enums `PrivacyRequestStatus` and `PrivacyRequestType`.
- Abilities `filagora.compliance.privacy_requests`, `.policies` and `.audit` (config `gates`), declared as entitlements.
