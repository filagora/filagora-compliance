# Filagora Compliance

Cookie consent, policy versions and privacy requests (export and erasure) for Filagora.

> **In progress, unreleased.** The package is at `0.1`: the API may change between `0.x` minor
> versions. Design: see the Filagora planning notes.

## What exists so far

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

Still to come: the privacy request workflow (export, erasure, mail), retention pruning, and the
Filament screens.

## Install

Not yet released.

## Testing

```bash
composer test
```

## Licence

MIT.
