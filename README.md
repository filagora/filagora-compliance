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

Still to come: the consent and acceptance services, the privacy request workflow (export,
erasure, mail), retention pruning, and the Filament screens.

## Install

Not yet released.

## Testing

```bash
composer test
```

## Licence

MIT.
