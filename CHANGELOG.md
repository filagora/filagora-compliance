# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- The eight tables (`cookie_consent_groups`, `cookie_consent_cookies`, `cookie_policy_versions`, `user_cookie_consents`, `privacy_policy_versions`, `user_privacy_policy_acceptances`, `privacy_requests`, `privacy_operation_audits`) in one migration, matching the site the package was extracted from index for index; a schema snapshot so `filagora:adopt` accepts an existing site's tables.
- Models (swappable through `filagora-compliance.models`) and the enums `PrivacyRequestStatus` and `PrivacyRequestType`.
- Abilities `filagora.compliance.privacy_requests`, `.policies` and `.audit` (config `gates`), declared as entitlements.
