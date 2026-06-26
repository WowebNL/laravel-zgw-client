# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-06-26

### Added

- `Enkelvoudiginformatieobjecten::download()` now accepts an optional array of query parameters, so
  a specific or point-in-time version of a document's content can be retrieved
  (`download($uuid, ['versie' => 2])`). The ZGW download operation defines the `versie` and
  `registratieOp` query parameters, which the method previously could not pass.

### Documentation

- A migration guide from the older `woweb/laravel-openzaak` package
  (`docs/migrating-from-openzaak.md`), linked from the README.
- README badges for the latest Packagist version, the minimum PHP version, the supported Laravel
  versions and the PHPStan level.
- Clarified that `show()`'s array argument is sent as the request's query parameters, so it carries
  any parameter the operation supports (for example `versie`, `registratieOp` and `datumGeldigheid`),
  not only `expand`. No signature change.

## [1.0.0] - 2026-06-25

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
- Typed DTO layer (opt-in, in the `Woweb\Zgw\Data` namespace), built additively on top of the
  array kernel. Tolerant hydration (missing fields become null, unknown fields are kept in
  `extra`, the raw response in `raw`), casts for dates, durations, references, enums and nested
  DTOs, and a `Typed::wrap()` decorator that turns an endpoint into one that returns DTOs while the
  array API stays available. Read DTOs are generated from the pinned OpenAPI specs with
  `composer dto:generate` (a require-dev tool, output committed) and kept in step with the specs by
  a coverage test. Writes use separate builders whose payload contains only the fields you set, so
  a PATCH never clears a field by accident. Read DTOs cover every read-capable resource across the
  six APIs, generated per component, with per-field `@since`/`@deprecated` version metadata verified
  against the specs.
- Typed polymorphic sub-objects: a `Rol`'s `betrokkeneIdentificatie` and a `ZaakObject`'s
  `objectIdentificatie` hydrate into the typed DTO their discriminator selects. Rol types all five
  `betrokkeneType` subtypes (an unknown value resolves to null); ZaakObject types the common
  `objectType` subtypes and keeps every other value as a raw array, so nothing is dropped. The
  generated resolvers are checked against the spec discriminators by the contract suite.
- Typed expanded responses: a resource fetched with `?expand=` carries a typed `_expand` DTO that
  hydrates the embedded related resources, including across components (an expanded zaak's
  `zaaktype` becomes a catalogi DTO and its `informatieobjecten` documenten DTOs), resolving
  recursively. `_expand` is null on a response that was not expanded, and a related resource the
  generator cannot map to a DTO degrades to a raw array rather than being dropped.
- Generated write builders for every write-capable resource (`Woweb\Zgw\Data\Writes\{Component}`),
  one typed setter per writable (not readOnly) field, normalising references, dates and enums. A
  contract test keeps the setters in step with the writable spec fields. Reference normalisation
  accepts a `Reference` or a URL string, dates accept a `DateTimeInterface` or a string.
- Layering gate (Deptrac): the array kernel (`Api`) may not depend on the optional typed layer
  (`Data`); the dependency may only point the other way. Enforced in CI.

### Changed

- Moved the `#[ZgwResource]` attribute from `Woweb\Zgw\Data\Attributes` to `Woweb\Zgw\Api\Attributes`.
  It annotates endpoints (the `Api` layer), so keeping it under `Data` made every endpoint depend on
  the typed layer. This only affects code that referenced the attribute class directly.

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