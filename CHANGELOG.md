# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.3] - 2026-07-14

### Fixed

- `Enkelvoudiginformatieobjecten::lock()` and the `publish()` action on `Zaaktypen`,
  `Besluittypen` and `Informatieobjecttypen` now send their POST request without a body. They
  previously sent Laravel's default empty-array data, which serializes to a JSON list (`[]`),
  making Open Zaak reject every attempt with a 400 "Ongeldige data. Verwacht een dictionary, kreeg
  een list." For lock this broke any flow that updates a document (such as uploading a new
  version). The ZGW standard defines these operations without a request body, with only a
  mandatory `Content-Type: application/json` header, which is still sent.

## [1.2.2] - 2026-07-03

### Fixed

- `Enkelvoudiginformatieobjecten::download()` now sends a wildcard `Accept` header instead of
  inheriting the connection-wide `Accept: application/json`. The download endpoint returns the raw
  file content, so restricting Accept to JSON made the server unable to satisfy the requested media
  type and respond `406 Not Acceptable`, breaking every document download. Other requests keep
  sending `Accept: application/json`.

## [1.2.1] - 2026-07-02

### Fixed

- `index()` on the non-paginated relation resources (`zaakinformatieobjecten`,
  `objectinformatieobjecten`, `besluitinformatieobjecten`, `gebruiksrechten`) now returns their items.
  The ZGW standard defines these list endpoints as a bare JSON array rather than a
  `{count,next,previous,results}` envelope, and `AbstractEndpoint::paginate()` only read the `results`
  key, so it silently yielded nothing (a zaak's linked documents could never be listed, for example).
  The paginator now detects a bare-array response and yields those items directly. Envelope responses
  are unchanged. The contract suite asserts, against the real specs across every supported release,
  that these endpoints stay a bare array, so a future move to pagination surfaces as a deliberate test
  break instead of a silent regression.

## [1.2.0] - 2026-06-26

### Added

- Duration write fields across the Catalogi write builders (a Zaaktype's `doorlooptijd`,
  `servicenorm` and `verlengingstermijn`, a Resultaattype's `archiefactietermijn` and
  `procestermijn`, a Besluittype's `publicatietermijn` and `reactietermijn`, a Statustype's
  `doorlooptijd`) now accept a `DateInterval` and normalise it to its ISO 8601 string, mirroring the
  `DurationCast` on the read side. A duration read from the API (a `CarbonInterval`) drops straight
  back into a write without the caller formatting it by hand. A string is still accepted unchanged.
- `Zgw::validate($name)` and `Zgw::validateAll()` validate a connection's configuration (secret
  strength, and that it is defined) without making any API call. Connections are built lazily, so a
  weak secret in an unused connection would otherwise surface only on first use; with one credential
  per municipality these let a deploy or boot check catch a bad credential for any connection up
  front. `validateAll()` names the first connection that fails.
- `ZgwConnection::assertUsable()` and `isUsable()` run a light read smoke-test (a catalogi
  `catalogussen` list, page 1) to confirm a connection works end to end, so a wrong base URL, an
  unreachable host or a credential the provider rejects is caught too. `assertUsable()` throws the
  underlying error; `isUsable()` returns a boolean for a health dashboard.
- The audit trail is now typed. A generated `AuditTrailData` (in the `Woweb\Zgw\Data\Generated\Audittrail`
  namespace, with a typed `bron` enum, an `aanmaakdatum` and the `wijzigingen` before and after)
  hydrates the audit-trail entries of any resource that exposes one. `TypedEndpoint::audittrail()`
  returns a `Collection<int, AuditTrailData>` and `audittrailItem()` a single `AuditTrailData`, so the
  audit trail is no longer the one read that stayed an untyped array. The DTO is generated from the
  spec and checked field by field by the contract suite, like the other read DTOs. The array API on
  the endpoint (`->endpoint()->audittrail()`) is unchanged.
- The base `Data` DTO now implements `Arrayable` and `JsonSerializable`. `$dto->toArray()` returns a
  ZGW-conformant array with the casts reversed (a `Reference` becomes its URL, a backed enum its
  value, a date or duration its ISO 8601 string, a nested DTO recurses), and `json_encode($dto)`
  yields the same structure. Date and date-time fields keep their original granularity, so a date
  stays `Y-m-d`. Forward-compatible fields kept in `extra` are preserved, so a round-trip drops
  nothing. A read DTO can now be reused in a write, cached or handed to Filament/Livewire without
  reaching for `->raw`.
- `Data::toWriteArray()` returns the write-shape of a DTO: the casts reversed like `toArray()`, but
  limited to the fields that were present in the source response, so a read value round-trips into a
  write identical to the source instead of emitting every declared field as null. Nested DTOs are
  write-shaped too.
- `WriteBuilder::identification()` sets a polymorphic identification field (a Rol's
  `betrokkeneIdentificatie`, a ZaakObject's `objectIdentificatie`) from a value read off the API.
  These fields are modelled polymorphically and have no generated typed setter; the helper accepts
  the typed sub-DTO (reduced to its write-shape) or the raw array kept for an untyped subtype, so a
  rol or zaakobject can be copied verbatim onto another resource without reaching for `->raw`.
- `Typed::wrap()` now carries a generated conditional return type, so the typed layer resolves to the
  concrete DTO. `Typed::wrap($conn->zaken()->zaken())->show($uuid)` statically resolves to `ZaakData`
  (and `->index()` to `LazyCollection<int, ZaakData>`) instead of the base `Data`, giving consumers
  full static typing with no runtime change and no configuration on their side (the type travels in
  the published docblock). The mapping is generated from `TypedMap` by `composer dto:generate`, so it
  cannot drift, and is verified by `assertType` checks under PHPStan.
- `Data\Values\Reference` now implements `JsonSerializable` and serialises to its bare URL string.
  A `Reference` read from a DTO can be placed straight into a write payload: `json_encode($reference)`
  yields the plain `"https://..."` link that ZGW expects, instead of a nested `{"url":...}` object.

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