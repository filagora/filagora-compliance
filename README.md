# Filagora Compliance

Cookie consent, policy versions and privacy requests (export and erasure) for Filagora, as a
Laravel 13 / Filament 5 plugin. Requires
[`filagora/filagora-core`](https://github.com/filagora/filagora-core). Who may use the screens is
answered by core's `AccessChecker`: install
[`filagora/filagora-entitlements`](https://github.com/filagora/filagora-entitlements) and it is
answered from entitlement grants, or bind your own checker. With neither, the screens deny
everyone.

> The package is at `0.1`: the API may change between `0.x` minor versions. There is no
> frontend: it is models, services, events, commands and staff screens, and you build the public
> forms (cookie banner, privacy request form) over the services.

## What it does

- **Tables and models** for cookie consent (groups, cookies, policy versions, each user's choice),
  the privacy policy (versions, each user's acceptance) and privacy requests (with legal hold
  and an operation audit). They match the site this package was extracted from, so
  `php artisan filagora:adopt` accepts that site's existing tables. IP addresses and user agents
  are stored only as hashes.
- **Abilities** `filagora.compliance.privacy_requests`, `.policies` and `.audit`, mapped to
  entitlement keys in `filagora-compliance.gates`.

- **Cookie consent** (`CookieConsent`): the enabled groups and cookies to show, the choice a
  signed-in user made (one row per user, replaced on each save, under the active policy
  version), and `requiresReconsent()` when the policy changed since. `normalize()` merges what
  was submitted over what the site declares: a required group is always on, a cookie's own
  choice wins over its group's, a group is on when any of its cookies is. Off when the setting
  `enable_cookie_consent_workflow` is off.
- **Privacy policy acceptance** (`PolicyAcceptance`): `hasAcknowledgedActivePolicy($user)` is
  cheap enough for a middleware (the active version is cached), `accept($user, $ip, $userAgent)`
  records the acceptance, `stateFor($user)` gives everything a profile page needs. Off when the
  setting `enforce_gdpr` is off.
- **Cookie policy versions** (`CookiePolicyVersions`) are numbered by day (`2026.10.14.1`). With
  `filagora/filagora-cms` installed, publishing the page named by the setting `cookie_policy_page`
  (a route beginning with `/`, or a page slug) creates a new version, so users are asked again.
- The package has no routes, views or JavaScript. Your endpoints are a few lines over the
  services, for example:

  ```php
  Route::post('/profile/cookie-consent', fn (Request $r, CookieConsent $consent) =>
      response()->json($consent->save($r->user(), $r->validate(['groups' => 'array', 'cookies' => 'array']), $r->ip(), $r->userAgent())->consent))
      ->middleware('auth');
  ```

- **Privacy requests** (`PrivacyRequests`): a person submits an export or erasure request (or
  rectification, restriction, objection, which staff handle by hand); staff review, hold, reply,
  reject, or fulfil it. `fulfilExport()` writes one JSON file with a section for the user and
  one for every `PrivacyContributor` any package or your application registered with core's
  `PrivacyContributorRegistry`; `fulfilErasure()` has each contributor erase its data and then
  your `UserAnonymizer` replaces who the user is, all in one transaction. A request on legal
  hold cannot be fulfilled. Every staff action is written to the operation audit, and the
  requester (and the address in the `dsar_request_email` setting) are emailed by events you can
  replace: set `mail.enabled` to false and listen to `PrivacyRequestSubmitted`,
  `PrivacyRequestStatusChanged` and `PrivacyReplySent` yourself. The mail's links are named routes
  of yours under `routes` in the config (a signed download route for exports, the page where a
  person sees their requests); any you leave empty is left out.
- **What erasure covers is up to what registers.** Register a contributor for your own tables
  (`app(PrivacyContributorRegistry::class)->register(...)`) and bind your own `UserAnonymizer` for
  user columns the default does not know (it blanks `name` and `email`, empties the verification
  date and makes the password unusable).

- **Retention**: `php artisan filagora:compliance:prune` (scheduled daily at 02:15; `--dry-run`
  reports without deleting) runs every `RetentionRule` registered with core's
  `RetentionRuleRegistry`, so each package says what it keeps and for how long. This package's
  rule deletes settled privacy requests after the windows in `retention.privacy_requests`
  (never one on legal hold) and removes export files 30 days after they were made. A rule that
  fails is reported, mailed to `dsar_request_email`, and does not stop the others.

- **Filament screens** (add `FilagoraCompliancePlugin::make()` to your panel), each behind its own
  ability, mapped to keys in `filagora-compliance.gates` (entitlement keys when entitlements is
  installed): privacy requests (`.privacy_requests.view` to look, `.privacy_requests` to act,
  `.erase` to erase a person or delete a request), cookie groups and cookie policy versions
  (`.cookie_policies`), privacy policy versions (`.privacy_policies`) and the operation audit
  (`.audit`). A request's page offers every step of its handling; the privacy KPIs
  (`kpis` in the config) can sit on your dashboard through `PrivacyKpiOverview`.

## Install

```bash
composer require filagora/filagora-compliance filagora/filagora-entitlements   # entitlements is optional
php artisan migrate
php artisan filagora:entitlements:sync   # with entitlements: creates the ability keys
```

```php
->plugin(\Filagora\Compliance\FilagoraCompliancePlugin::make())
```

Then set the `dsar_request_email` setting (who is told about new requests), schedule your
application's `schedule:run` (the daily prune is registered by the package), and read
`config/filagora-compliance.php` for the links in the mail, where exports are written and the
retention windows. To start from a database that already holds these tables, run
`php artisan filagora:adopt` instead of `migrate`.

## Testing

```bash
composer test
```

## Licence

MIT.
