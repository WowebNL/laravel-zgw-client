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