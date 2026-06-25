# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - Unreleased

### Added

- Initial release as complete rewrite of `woweb/openzaak`.
- Autorisaties API (`->autorisaties()`): `applicaties()` with full CRUD and a `consumer()` lookup by client id.
- Notificaties API (`->notificaties()`): `abonnementen()`, `kanalen()` and `notificaties()->send()`.
- Version awareness: a connection records the ZGW release it targets via the `version` config
  (env `ZGW_VERSION`, default `1.7`), exposed as the `ZgwVersion` enum through
  `$connection->getVersion()`.
- `ValidationException` (extends `ApiRequestException`): structured access to a ZGW `ValidatieFout`
  body via `invalidParams()`, `validationCode()`, `title()` and `detail()`.
- Full endpoint coverage: every operation defined by the ZGW standard (releases 1.5, 1.6, 1.7) is
  now implemented across all six APIs, enforced as a hard requirement by the contract test suite.
  New Zaken endpoints (`klantcontacten()`, `zaakcontactmomenten()`, `zaakverzoeken()`,
  `zaaknotities()`, nested `zaken()->besluiten()`), new Catalogi types (`besluittypen()`,
  `zaakobjecttypen()`, `zaaktypeInformatieobjecttypen()`), and new actions: `zoek()` search,
  `publish()`, document `download()`, `audittrailItem()`, `reserveerZaaknummer()`, and `put()` on
  the resources that support it.
- Per-version operation guard: calling an operation that the connection's targeted release does not
  define throws `UnsupportedOperationException` before any request is sent. The available releases
  per operation live in `OperationAvailability` and are verified against the specs by the contract
  tests.
- Capability interfaces in `Woweb\Zgw\Contracts` (`ListsResources`, `ShowsResource`,
  `CreatesResource`, `PatchesResource`, `ReplacesResource`, `DeletesResource`, `PublishesResource`,
  `SearchesResources`, `ProvidesAuditTrail`), one per action trait. Each endpoint declares the
  operations it supports through its `implements` clause, and each trait requires its interface via
  `@phpstan-require-implements`, so a missing declaration is a static error rather than a runtime
  surprise.
- Opt-in transient-failure retries per connection (`retry_times`, `retry_sleep_ms`,
  `retry_max_sleep_ms`), off by default. Only idempotent requests are retried, only on a connection
  error, a 429 or a 5xx, honouring a `Retry-After` header. Create and update calls are never retried.
- A `ZgwRequestSent` event dispatched after every response, for request-level audit logging.

### Changed

- `index()` now returns a `LazyCollection` that paginates on demand instead of an eagerly
  realised `Collection`. Iterate it, or call `->all()` / `->collect()` to realise everything
  (and surface any error) immediately.
- Base URLs are now the full URL of each API, including the version path, taken straight from the
  environment (for example `ZGW_ZAKEN_BASE_URL=https://openzaak.example.com/zaken/api/v1/`). The
  package no longer appends `/{api}/api/v1/`, so any deployment topology is supported.

### Removed

- Removed endpoint methods that the ZGW API does not support, so the client only exposes
  operations defined by the official specs (1.5, 1.6, 1.7):
  - `Statussen::delete()` (a status is append-only)
  - `Rollen::patch()` (a rol is immutable)
  - `Catalogussen::delete()` (a catalogus cannot be deleted)
  - `Objectinformatieobjecten::patch()` and `Besluitinformatieobjecten::patch()` (relation
    resources cannot be updated)