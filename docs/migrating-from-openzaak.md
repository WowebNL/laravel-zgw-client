# Migrating from `woweb/laravel-openzaak`

This guide maps the older `woweb/openzaak` package (namespace `Woweb\Openzaak`, the `Openzaak` facade) onto `woweb/laravel-zgw-client` (namespace `Woweb\Zgw`, the `Zgw` facade).

The new package is a ground-up rewrite focused strictly on the ZGW standard. The overall shape is familiar (a connection, then an API, then an endpoint, then an action), but method names, return types and configuration have changed, and a few features were intentionally dropped.

## Contents

- [Scope: what is and is not covered](#scope-what-is-and-is-not-covered)
- [1. Install](#1-install)
- [2. Configuration and environment](#2-configuration-and-environment)
- [3. The fluent call and named connections](#3-the-fluent-call-and-named-connections)
- [4. Action method renames](#4-action-method-renames)
- [5. Return types: Collection becomes array (or DTO)](#5-return-types-collection-becomes-array-or-dto)
- [6. Resolving a resource by URL](#6-resolving-a-resource-by-url)
- [7. Caching](#7-caching)
- [8. Authentication and the JWT](#8-authentication-and-the-jwt)
- [9. New capabilities worth adopting](#9-new-capabilities-worth-adopting)
- [Quick reference](#quick-reference)

## Scope: what is and is not covered

The new package covers the six ZGW APIs: Zaken, Catalogi, Documenten, Besluiten, and (new) Autorisaties and Notificaties.

It does **not** include the Objects API (`ObjectsApi`) or the Open Klant (`OpenKlant`) integrations that shipped with `laravel-openzaak`. Those are not part of the ZGW standard, so they are out of scope here. If you depend on them, keep `woweb/openzaak` installed alongside the new package for those calls only, or move them to a dedicated client. The two packages use different namespaces and facades, so they coexist without conflict.

## 1. Install

```bash
composer require woweb/laravel-zgw-client
php artisan vendor:publish --tag=zgw-config
```

Keep `woweb/openzaak` installed only if you still need its Objects API or Open Klant features; otherwise remove it once the migration is complete.

## 2. Configuration and environment

The config file changes from `config/openzaak.php` to `config/zgw.php`, and the environment keys change from `OPENZAAK_*` to `ZGW_*`.

The most important change: each base URL is now the **full URL of that API, including the version path and a trailing slash**. The old package combined a root `OPENZAAK_URL` with per-API overrides and appended path segments itself. The new package appends nothing, which is what lets it support any deployment topology.

| Old (`OPENZAAK_*`) | New (`ZGW_*`) | Notes |
|---|---|---|
| `OPENZAAK_CLIENT_ID` | `ZGW_CLIENT_ID` | Unchanged in meaning. |
| `OPENZAAK_CLIENT_SECRET` | `ZGW_CLIENT_SECRET` | Must be at least 32 bytes. The HS256 floor is enforced (a shorter secret throws `WeakSecretException`). |
| `OPENZAAK_ZAKEN_BASE_URL` | `ZGW_ZAKEN_BASE_URL` | Now the full URL including the version path, for example `https://openzaak.example.com/zaken/api/v1/`. |
| `OPENZAAK_CATALOGI_BASE_URL`, `OPENZAAK_CATALOGI_URL` | `ZGW_CATALOGI_BASE_URL` | One full URL now. |
| `OPENZAAK_DOCUMENTEN_BASE_URL` | `ZGW_DOCUMENTEN_BASE_URL` | Full URL. |
| `OPENZAAK_BESLUITEN_BASE_URL` | `ZGW_BESLUITEN_BASE_URL` | Full URL. |
| `OPENZAAK_URL` (single root host) | (removed) | Set each `ZGW_*_BASE_URL` you use instead. There is no single root URL. |
| (none) | `ZGW_AUTORISATIES_BASE_URL` | New API. |
| (none) | `ZGW_NOTIFICATIES_BASE_URL` | New API. |
| (none) | `ZGW_VERSION` | The ZGW release this connection targets (`1.5`, `1.6` or `1.7`, default `1.7`). |
| (none) | `ZGW_USER_ID`, `ZGW_USER_REPRESENTATION` | Explicit JWT claims (see [section 8](#8-authentication-and-the-jwt)). |
| `OPENZAAK_ACCEPT_CRS` | `ZGW_ACCEPT_CRS` | Unchanged in meaning. |
| `OPENZAAK_CONTENT_CRS` | `ZGW_CONTENT_CRS` | Unchanged in meaning. |
| `OPENZAAK_DATE_FORMAT` | (removed) | The typed layer casts dates; write builders accept a `DateTimeInterface` or a string. |
| `OPENZAAK_USER_JWT` | (removed) | See [section 8](#8-authentication-and-the-jwt). |
| `cache.default`, `cache.time.*` | `ZGW_CACHE_STORE` plus per-call `->cache()` | See [section 7](#7-caching). |
| `zaakeigenschappen.formio_reference` | (removed) | Application concern, not part of the ZGW client. |
| `objectsapi.*`, `openklant.*` | (removed) | Out of scope (see [Scope](#scope-what-is-and-is-not-covered)). |

A minimal new `.env`:

```dotenv
ZGW_CONNECTION=main
ZGW_VERSION=1.7

ZGW_ZAKEN_BASE_URL=https://openzaak.example.com/zaken/api/v1/
ZGW_CATALOGI_BASE_URL=https://openzaak.example.com/catalogi/api/v1/
ZGW_DOCUMENTEN_BASE_URL=https://openzaak.example.com/documenten/api/v1/
ZGW_BESLUITEN_BASE_URL=https://openzaak.example.com/besluiten/api/v1/

ZGW_CLIENT_ID=your-client-id
ZGW_CLIENT_SECRET=your-client-secret-of-at-least-32-bytes
ZGW_USER_ID=your-user-id
ZGW_USER_REPRESENTATION=Your Name
```

## 3. The fluent call and named connections

The new package selects a named connection before choosing an API. The old package used a single implicit connection (or one passed to `new Openzaak($connection)`).

```php
// Old
use Woweb\Openzaak\Openzaak;

$zaken = Openzaak::zaken()->zaken()->getAll();

// New
use Woweb\Zgw\Facades\Zgw;

$zaken = Zgw::connection('main')->zaken()->zaken()->index();
```

For multiple providers or credentials, define several connections in `config/zgw.php` under `connections` and select them by name, instead of constructing a connection object by hand:

```php
Zgw::connection('secondary')->documenten()->enkelvoudiginformatieobjecten()->index();
```

## 4. Action method renames

| Old | New |
|---|---|
| `getAll(array $params = [])` | `index(array $params = [])` |
| `get(string $uuid, array $expand = [])` | `show(string $uuid, array $expand = [])` |
| `store(array $params)` | `store(array $params)` (unchanged) |
| `patch(string $uuid, array $params)` | `patch(string $uuid, array $params)` (unchanged) |
| `put(string $uuid, array $params)` | `put(string $uuid, array $params)` (unchanged) |
| `delete(string $uuid)` | `delete(string $uuid)` (unchanged) |
| `getAllRaw()`, `getAllUrl()` | (removed) Use `index()`, or build the request through the endpoint. |

The two renames that touch most call sites are `getAll()` to `index()` and `get()` to `show()`.

## 5. Return types: Collection becomes array (or DTO)

This is the largest behavioural change. The old package returned `Illuminate\Support\Collection` instances (nested collections). The new package returns plain arrays, and `index()` returns a `LazyCollection` of arrays that paginates on demand.

```php
// Old: a Collection
$zaak = Openzaak::zaken()->zaken()->get($uuid);
$identificatie = $zaak->get('identificatie');

// New: a plain array
$zaak = Zgw::connection('main')->zaken()->zaken()->show($uuid);
$identificatie = $zaak['identificatie'];
```

If you want typed access back, opt into the typed layer instead of reading array keys:

```php
use Woweb\Zgw\Data\Typed;

$zaak = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->show($uuid);

$zaak->identificatie;                 // string
$zaak->startdatum;                    // CarbonImmutable|null
$zaak->vertrouwelijkheidaanduiding;   // a backed enum, or null
```

For lists, note that `index()` is lazy where the old `getAll()` was eager. Iterating with `foreach` works unchanged. To realise everything at once (and surface any error immediately), call `->all()` or `->collect()`:

```php
$all = Zgw::connection('main')->zaken()->zaken()->index()->all();
```

If a call site relied on `Collection` methods (`->map()`, `->filter()`, `->pluck()` and so on) on a single result, wrap the array yourself with `collect($result)`, or adopt the typed DTO.

## 6. Resolving a resource by URL

The old `Openzaak::get($url)`, `getRaw($url)` and `getCached($url)` helpers become `DirectEndpoint`:

```php
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;

$connection = Zgw::connection('main');
$resource = (new DirectEndpoint($connection))->getByUrl('https://openzaak.example.com/zaken/api/v1/zaken/uuid');
```

The target URL must resolve to a trusted origin (the configured base URLs plus any `allowed_hosts`), otherwise a `DisallowedHostException` is thrown. This host allowlisting is new and is on by default.

## 7. Caching

Caching moves from a config-driven, partly default-on model to an explicit, opt-in, per-call model.

- Old: `cache.default` plus per-API `cache.time.*`, with a `getCached()` helper.
- New: call `->cache($ttl)` on `index()` or `show()`. Write actions are never cached.

```php
$zaaktypen = Zgw::connection('main')->catalogi()->zaaktypen()->cache(300)->index();
```

Set `ZGW_CACHE_STORE` to route ZGW caching to a dedicated store. Cache entries are namespaced per `client_id`, so connections with different credentials never share an entry. Because ZGW responses can contain personal data, prefer short TTLs and a dedicated or encrypted store.

## 8. Authentication and the JWT

The old package could store a generated JWT on the authenticated user model (`OPENZAAK_USER_JWT`, with `openzaak_jwt` and `openzaak_jwt_valid_till` columns) and reuse it until it expired.

The new package generates a short-lived, stateless HS256 JWT per request. There is nothing to persist:

- Set the claims explicitly with `ZGW_USER_ID` and `ZGW_USER_REPRESENTATION`.
- Tune the lifetime with `ZGW_JWT_EXPIRY` (seconds, default 300).
- The `client_secret` must be at least 32 bytes (the HS256 floor, enforced).

You can drop the `openzaak_jwt` and `openzaak_jwt_valid_till` columns from your user table once the old package is removed.

## 9. New capabilities worth adopting

These did not exist in `laravel-openzaak` and are the reason to migrate beyond a like-for-like swap:

- The **Autorisaties** and **Notificaties** APIs (`->autorisaties()`, `->notificaties()`).
- A **per-version operation guard**: calling an operation the targeted ZGW release does not define throws `UnsupportedOperationException` before any request is sent.
- The **typed DTO layer** (DTOs, typed polymorphic sub-objects, typed `?expand=` responses, and write builders), kept in step with the specs by the contract suite.
- **Write builders** with presence-based PATCH semantics, so a partial update never clears a field you did not set.
- **Opt-in retries** for transient failures (idempotent requests only, honouring `Retry-After`) and a `ZgwRequestSent` event for audit logging.

## Quick reference

| Concern | Old | New |
|---|---|---|
| Package | `woweb/openzaak` | `woweb/laravel-zgw-client` |
| Namespace | `Woweb\Openzaak` | `Woweb\Zgw` |
| Facade / entry | `Openzaak` | `Zgw` |
| First call | `Openzaak::zaken()` | `Zgw::connection('main')->zaken()` |
| List | `getAll($params)` | `index($params)` |
| Single | `get($uuid, $expand)` | `show($uuid, $expand)` |
| Create / update / replace / delete | `store` / `patch` / `put` / `delete` | same |
| Result of a read | `Illuminate\Support\Collection` | array, or DTO via `Typed::wrap(...)` |
| List result | eager `Collection` | lazy `LazyCollection` of arrays |
| URL fetch | `Openzaak::get($url)` | `(new DirectEndpoint($connection))->getByUrl($url)` |
| Caching | config `cache.*` + `getCached()` | per-call `->cache($ttl)` |
| Config file | `config/openzaak.php` | `config/zgw.php` |
| Env prefix | `OPENZAAK_` | `ZGW_` |
| APIs | zaken, catalogi, documenten, besluiten | the four plus autorisaties and notificaties |
| Objects API, Open Klant | included | not included (out of scope) |
