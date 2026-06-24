# Laravel ZGW client

Laravel package for interacting with the Dutch ZGW (Zaakgericht Werken) APIs: Zaken, Catalogi, Documenten, Besluiten, Autorisaties and Notificaties. It supports the ZGW standard releases 1.5, 1.6 and 1.7.

**Requirements:** PHP 8.2+, Laravel 12 or 13, firebase/php-jwt 7

## About

This package is developed and maintained by [Woweb](https://www.woweb.nl). It provides a clean Laravel integration for the Dutch ZGW (Zaakgericht Werken) standard, used in Dutch government applications for case management.

It is a client (consumer) for the ZGW APIs. It builds a signed JWT bearer token from your configured credentials and issues outbound HTTPS requests to a ZGW provider such as OpenZaak. The payloads it transports often contain citizen case data and personal data, so a few security defaults are enabled out of the box. Read [Behaviour to be aware of](#behaviour-to-be-aware-of) before going to production.

## Installation

```bash
composer require woweb/laravel-zgw-client
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=zgw-config
```

## Configuration

### Quick start

Add the following to your `.env`:

```dotenv
ZGW_CONNECTION=main
ZGW_VERSION=1.7

ZGW_ZAKEN_BASE_URL=https://openzaak.example.com/zaken/api/v1/
ZGW_CATALOGI_BASE_URL=https://openzaak.example.com/catalogi/api/v1/
ZGW_DOCUMENTEN_BASE_URL=https://openzaak.example.com/documenten/api/v1/
ZGW_BESLUITEN_BASE_URL=https://openzaak.example.com/besluiten/api/v1/
ZGW_AUTORISATIES_BASE_URL=https://openzaak.example.com/autorisaties/api/v1/
ZGW_NOTIFICATIES_BASE_URL=https://openzaak.example.com/notificaties/api/v1/

ZGW_CLIENT_ID=your-client-id
ZGW_CLIENT_SECRET=your-client-secret
ZGW_USER_ID=your-user-id
ZGW_USER_REPRESENTATION=Your Name
```

Each base URL is the full URL of that API, including the version path and a trailing slash. This supports any deployment topology: OpenZaak's single host (`https://openzaak.example.com/zaken/api/v1/`) as well as one host per API (`https://zaken-api.example.com/api/v1/`). Only configure the APIs you use. `ZGW_VERSION` records which ZGW standard release the connection targets (`1.5`, `1.6` or `1.7`); see [Version awareness](#version-awareness).

> **Important:** the `client_secret` is the HS256 signing key, so it must be at least 32 bytes. This floor is enforced twice: the package validates the secret when a connection is built (throwing `WeakSecretException`), and `firebase/php-jwt` 7 refuses to sign with a shorter key (`DomainException: Provided key is too short`). The default `secret_rules.min_length` of 32 matches that floor. You can relax the package rules per connection, but no setting can take an HS256 secret below 32 bytes. See [Secret strength](#secret-strength).

### Full configuration reference

Every key below lives inside a connection (the `main` connection by default). All keys except `urls`, `client_id`, `client_secret`, `user_id` and `user_representation` are optional and have safe defaults.

| Key | Env | Default | Purpose |
|---|---|---|---|
| `urls.zaken` etc. | `ZGW_*_BASE_URL` | `''` | Full base URL per ZGW API, including the version path (`zaken`, `catalogi`, `documenten`, `besluiten`, `autorisaties`, `notificaties`). |
| `version` | `ZGW_VERSION` | `1.7` | ZGW standard release the connection targets (`1.5`, `1.6`, `1.7`). |
| `client_id` | `ZGW_CLIENT_ID` | `''` | JWT `client_id` / `iss`. Issued by your provider. |
| `client_secret` | `ZGW_CLIENT_SECRET` | `''` | HS256 signing secret. Issued by your provider. |
| `user_id` | `ZGW_USER_ID` | `''` | JWT `user_id` claim. |
| `user_representation` | `ZGW_USER_REPRESENTATION` | `''` | JWT `user_representation` claim. |
| `jwt_expiry` | `ZGW_JWT_EXPIRY` | `300` | Lifetime in seconds for the JWT `exp` claim. `0` omits `exp`. |
| `secret_rules.min_length` | `ZGW_SECRET_MIN_LENGTH` | `32` | Minimum `client_secret` length. `0` disables the length check. |
| `secret_rules.require_uppercase` | `ZGW_SECRET_REQUIRE_UPPERCASE` | `false` | Require an uppercase letter. |
| `secret_rules.require_lowercase` | `ZGW_SECRET_REQUIRE_LOWERCASE` | `false` | Require a lowercase letter. |
| `secret_rules.require_number` | `ZGW_SECRET_REQUIRE_NUMBER` | `false` | Require a digit. |
| `secret_rules.require_symbol` | `ZGW_SECRET_REQUIRE_SYMBOL` | `false` | Require a non-alphanumeric character. |
| `cache_store` | `ZGW_CACHE_STORE` | `null` | Cache store for `->cache()`. `null` uses the app default. |
| `accept_crs` | `ZGW_ACCEPT_CRS` | `EPSG:4326` | `Accept-Crs` header. |
| `content_crs` | `ZGW_CONTENT_CRS` | `EPSG:4326` | `Content-Crs` header. |
| `allowed_hosts` | (none) | `[]` | Extra trusted origins for `next` links and direct URL fetches. |
| `connect_timeout` | `ZGW_CONNECT_TIMEOUT` | `10` | TCP/TLS handshake timeout in seconds. |
| `timeout` | `ZGW_TIMEOUT` | `30` | Total request timeout in seconds. |
| `max_pages` | `ZGW_MAX_PAGES` | `1000` | Maximum pages followed during auto-pagination. |

The top-level `default` key (`ZGW_CONNECTION`, default `main`) selects which connection is used when you call `connection()` without a name.

## Usage

The package registers `ZgwManager` in the service container. Reach it through the `Zgw` facade or through dependency injection. The fluent chain is always `connection()` then an API (`zaken()`, `catalogi()`, `documenten()`, `besluiten()`, `autorisaties()`, `notificaties()`) then an endpoint then an action.

### Via the Zgw facade

```php
use Woweb\Zgw\Facades\Zgw;

// Fetch all zaken (auto-paginates across pages)
$zaken = Zgw::connection('main')->zaken()->zaken()->index();

// Fetch all zaken matching a filter (query parameters)
$open = Zgw::connection('main')->zaken()->zaken()->index(['status' => 'open']);

// Fetch a single zaak
$zaak = Zgw::connection('main')->zaken()->zaken()->show('uuid-here');

// Create a zaak
$zaak = Zgw::connection('main')->zaken()->zaken()->store([
    'bronorganisatie'              => '123456789',
    'zaaktype'                     => 'https://openzaak.example.com/catalogi/api/v1/zaaktypen/uuid',
    'verantwoordelijkeOrganisatie' => '123456789',
    'startdatum'                   => '2024-01-01',
]);

// Partial update
$zaak = Zgw::connection('main')->zaken()->zaken()->patch('uuid-here', ['omschrijving' => 'Updated']);

// Full replace
$zaak = Zgw::connection('main')->zaken()->zaken()->put('uuid-here', [/* ... */]);

// Delete
Zgw::connection('main')->zaken()->zaken()->delete('uuid-here');

// Nested resource: zaakeigenschappen of a specific zaak
$eigenschappen = Zgw::connection('main')->zaken()->zaken()->zaakeigenschappen('zaak-uuid')->index();
```

### Via dependency injection

`ZgwManager` can be injected directly. The default connection is used when no name is passed to `connection()`.

```php
use Illuminate\Support\LazyCollection;
use Woweb\Zgw\ZgwManager;

class ZaakService
{
    public function __construct(private readonly ZgwManager $zgw) {}

    public function getZaken(): LazyCollection
    {
        return $this->zgw->connection()->zaken()->zaken()->index();
    }
}
```

### Available APIs and endpoints

| Connection method | API | Endpoints |
|---|---|---|
| `->zaken()` | Zaken API v1 | `zaken()`, `statussen()`, `rollen()`, `resultaten()`, `zaakinformatieobjecten()`, `zaakobjecten()` |
| `->catalogi()` | Catalogi API v1 | `catalogussen()`, `zaaktypen()`, `informatieobjecttypen()`, `roltypen()`, `statustypen()`, `resultaattypen()`, `eigenschappen()` |
| `->documenten()` | Documenten API v1 | `enkelvoudiginformatieobjecten()`, `gebruiksrechten()`, `objectinformatieobjecten()`, `verzendingen()`, `bestandsdelen()` |
| `->besluiten()` | Besluiten API v1 | `besluiten()`, `besluitinformatieobjecten()` |
| `->autorisaties()` | Autorisaties API v1 | `applicaties()` |
| `->notificaties()` | Notificaties API v1 | `abonnementen()`, `kanalen()`, `notificaties()` |

### Available actions per endpoint

| Method | HTTP | Returns | Description |
|---|---|---|---|
| `index(array $params = [])` | GET | `LazyCollection` | List resources, paginating on demand. `$params` become query filters. |
| `show(string $uuid, array $expand = [])` | GET | `array` | Fetch a single resource. |
| `store(array $params)` | POST | `array` | Create a resource. |
| `patch(string $uuid, array $params)` | PATCH | `array` | Partial update. |
| `put(string $uuid, array $params)` | PUT | `array` | Full replace. |
| `delete(string $uuid)` | DELETE | `bool` | Delete a resource. Returns `true` on HTTP 204. |

Not every endpoint implements every action; each endpoint only mixes in the actions the ZGW API actually supports for that resource. A `status` and a `rol`, for example, cannot be updated, and `bestandsdelen()` only exposes `put()`. Some endpoints add resource-specific methods (see [Document operations](#document-operations), [Autorisaties and Notificaties](#autorisaties-and-notificaties)).

### Pagination

`index()` returns a [LazyCollection](https://laravel.com/docs/collections#lazy-collections) that follows the API's `next` links on demand. Iterating the collection drives the HTTP requests one page at a time, so a large result set is streamed instead of being buffered in memory, and `->take(n)` stops fetching as soon as it has enough. When a result item has no `uuid` key, the UUID is derived from its `url` field. The number of pages followed is bounded by `max_pages` (see [Pagination limit](#pagination-limit)).

```php
// Streams pages as you iterate; only the pages needed are fetched.
foreach (Zgw::connection('main')->zaken()->zaken()->index(['rol__betrokkeneType' => 'natuurlijk_persoon']) as $zaak) {
    // ...
}

// Realise everything eagerly into a Collection (and trigger any error now):
$all = Zgw::connection('main')->zaken()->zaken()->index()->collect();
```

Because the work is deferred, the first request and any error (a failed response, an untrusted `next` host, the page limit) surface while the collection is iterated, not when `index()` returns. Call `->all()` or `->collect()` to force this immediately. When `->cache()` is used, the pages are realised eagerly so the result can be stored.

### Document operations

`enkelvoudiginformatieobjecten()` exposes the standard actions plus lock handling and an audit trail.

```php
$doc = Zgw::connection('main')->documenten()->enkelvoudiginformatieobjecten();

// Lock a document before updating, returns the lock string
$lockString = $doc->lock('document-uuid');

// Full replace (the lock string must be in the payload)
$doc->put('document-uuid', [/* ... */, 'lock' => $lockString]);

// Unlock
$doc->unlock('document-uuid', $lockString);

// Retrieve audit trail
$trail = $doc->audittrail('document-uuid');
```

`besluiten()` also exposes `audittrail('besluit-uuid')`.

### Autorisaties and Notificaties

The Autorisaties API manages the `applicaties` that hold authorisations, plus a lookup by client id.

```php
$applicaties = Zgw::connection('main')->autorisaties();

$applicaties->applicaties()->index();                 // list applicaties (LazyCollection)
$applicaties->applicaties()->store([/* ... */]);      // register an applicatie
$applicaties->applicaties()->consumer('your-client'); // the applicatie for a given client id
```

The Notificaties API manages subscriptions (`abonnementen`), channels (`kanalen`) and publishing a notification.

```php
$nrc = Zgw::connection('main')->notificaties();

// Subscribe to events
$nrc->abonnementen()->store([
    'callbackUrl' => 'https://your-app.example.com/zgw/webhook',
    'auth'        => 'Bearer your-callback-token',
    'kanalen'     => [['naam' => 'zaken', 'filters' => []]],
]);

// List channels (create + read only)
$nrc->kanalen()->index();

// Publish a notification
$nrc->notificaties()->send([
    'kanaal'     => 'zaken',
    'hoofdObject'=> 'https://openzaak.example.com/zaken/api/v1/zaken/uuid',
    'resource'   => 'status',
    'actie'      => 'create',
    'aanmaakdatum' => '2024-01-01T12:00:00Z',
]);
```

### Version awareness

A connection records which ZGW standard release it targets (`version` config, env `ZGW_VERSION`, default `1.7`). It is exposed as a `ZgwVersion` enum so calling code can branch on it; an unsupported value throws `InvalidConfigurationException`.

```php
use Woweb\Zgw\Enums\ZgwVersion;

$version = Zgw::connection('main')->getVersion();   // ZgwVersion::V1_7

if ($version->isAtLeast(ZgwVersion::V1_6)) {
    // use functionality introduced in ZGW 1.6
}
```

The package surface is shared across releases. The version is informational: it does not change which endpoints are available, but lets your application adapt to the provider it talks to. The package is validated against the OpenAPI specs of releases 1.5, 1.6 and 1.7 (see [Testing](#testing)).

### Multiple connections

Define more than one connection to talk to multiple providers or multiple credentials.

```php
'connections' => [
    'main'      => [/* ... */],
    'secondary' => [/* ... */],
],
```

```php
Zgw::connection('secondary')->documenten()->enkelvoudiginformatieobjecten()->index();
```

### Resolving resources by URL

ZGW responses contain hyperlinks to related resources. `DirectEndpoint` fetches such a URL directly.

```php
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;

$connection = Zgw::connection('main');
$resource = (new DirectEndpoint($connection))->getByUrl('https://openzaak.example.com/zaken/api/v1/zaken/uuid');
```

The target URL must resolve to a trusted origin, otherwise a `DisallowedHostException` is thrown. See [Host allowlist](#host-allowlist).

## Caching

Caching is opt-in per request through the fluent `cache()` method.

```php
// Cache this result for 5 minutes
$zaaktypen = Zgw::connection('main')->catalogi()->zaaktypen()->cache(300)->index();

// Cache for the default 60 seconds
$zaaktypen = Zgw::connection('main')->catalogi()->zaaktypen()->cache()->index();
```

Only `index()` and `show()` are cacheable. Write actions are never cached.

**What an implementer must know about the cache:**

1. **It stores response data, which may contain personal data.** When you enable `->cache()`, the decoded ZGW response is written to a cache store. For citizen case data this is regulated personal data. Prefer a dedicated or encrypted store and keep TTLs short.
2. **Choose where it lands.** By default the application's default cache store is used. Set `cache_store` on the connection (env `ZGW_CACHE_STORE`) to route ZGW caching to a dedicated store, separate from the rest of your application cache.
3. **Cache entries are isolated per credential.** The cache key is namespaced by `client_id` and hashed with SHA-256. Two connections to the same host but with different credentials (and therefore different authorizations) never share a cache entry, so a narrowly scoped connection cannot read data cached by a broadly scoped one.
4. **Keep TTLs short.** ZGW JWTs and authorizations cannot be revoked from the cache. A short TTL limits how long stale or sensitive data lingers.

## Behaviour to be aware of

This package ships with several security defaults. Most are transparent, but each can throw or block, so an implementer should know they exist.

### JWT and token lifetime

Each request is authenticated with a freshly minted HS256 JWT carrying `iss`, `iat`, `client_id`, `user_id`, `user_representation` and an `exp` claim. The claim set matches the official VNG reference implementation. The lifetime comes from `jwt_expiry` (default 300 seconds). Tokens are minted per request, so a short lifetime is sufficient and covers one request plus the provider's clock-skew leeway. If your provider rejects tokens that carry `exp`, set `jwt_expiry` to `0` to omit the claim.

### Secret strength

The `client_secret` is validated when a connection is built, because it is the signing key. By default it must be at least 32 bytes. A secret that fails the rules throws `WeakSecretException` before any request is sent. Tune the policy per connection through `secret_rules`:

```php
'secret_rules' => [
    'min_length'        => 32,    // lower to relax the package check (but see the floor below)
    'require_uppercase' => false,
    'require_lowercase' => false,
    'require_number'    => false,
    'require_symbol'    => false,
],
```

Setting `min_length` to `0` with all character classes `false` disables the package's own validation, so you can use a secret of any composition (for example all lowercase, no symbols) for that connection.

**The 32-byte floor cannot be removed for HS256.** Disabling the package validation does not allow an arbitrarily short secret. `firebase/php-jwt` 7 enforces the RFC 7518 minimum HMAC key length, so an HS256 secret shorter than 32 bytes is rejected at sign time with `DomainException: Provided key is too short`, no matter what `secret_rules` says. In practice `secret_rules` lets you relax the composition requirements and adjust the length down to 32 bytes; 32 bytes is the immovable lower bound for HS256.

### Host allowlist

When `index()` follows a `next` link, or when you call `DirectEndpoint::getByUrl()`, the package only sends the bearer token to a trusted origin. Trusted origins are the configured base URLs of the connection plus anything in `allowed_hosts`. A request to any other origin throws `DisallowedHostException` before the token leaves your application. This prevents a tampered or unexpected response from redirecting your credential to an untrusted host. If your provider legitimately returns links to a different host (for example a separate document download domain), add that origin to `allowed_hosts` (a full URL or a bare host, which is treated as https).

### Timeouts

Every request carries `connect_timeout` (default 10 seconds) and `timeout` (default 30 seconds), so a slow or unresponsive provider cannot hang your application workers indefinitely. Tune them per connection.

### Pagination limit

Auto-pagination follows at most `max_pages` pages (default 1000). If a provider returns an unbounded chain of `next` links, the package stops and throws `PaginationLimitException` instead of looping forever. Raise `max_pages` for genuinely large result sets, or narrow the query with filters passed to `index()`.

### Identifier validation

Resource identifiers passed to `show()`, `patch()`, `put()`, `delete()`, the document lock methods and the nested `zaakeigenschappen()` must consist only of letters, digits, hyphen and underscore. Normal ZGW UUIDs satisfy this. Any value containing a slash, dot, query character or whitespace throws `InvalidIdentifierException`, which prevents path or query injection into the request URL. If you derive identifiers from user input, expect this exception for malformed values.

## Error handling

Every failed response (HTTP status 400 and above, or a delete that does not return 204) throws an `ApiRequestException`. To keep potential personal data out of your logs, the exception message contains only the status code. The full response body is available on the attached response object for deliberate inspection.

```php
use Woweb\Zgw\Exceptions\ApiRequestException;

try {
    $zaak = Zgw::connection('main')->zaken()->zaken()->show('uuid-here');
} catch (ApiRequestException $e) {
    $status = $e->getResponse()->status();   // e.g. 404
    $body   = $e->getResponse()->json();     // full decoded body, inspect deliberately
    // handle as appropriate
}
```

### Validation errors

When a write is rejected with a structured ZGW `ValidatieFout` body (an `invalidParams` array, typically HTTP 400), a `ValidationException` is thrown instead. It extends `ApiRequestException`, so existing handlers keep working, and adds typed access to the per-field failures without exposing the body in the (auto-logged) message.

```php
use Woweb\Zgw\Exceptions\ValidationException;

try {
    Zgw::connection('main')->zaken()->zaken()->store($payload);
} catch (ValidationException $e) {
    $e->validationCode();   // top-level ZGW code, e.g. "invalid"
    $e->title();            // e.g. "Invalid input."

    foreach ($e->invalidParams() as $param) {
        // $param->name, $param->code, $param->reason
    }
}
```

### Exception reference

All exceptions extend `Woweb\Zgw\Exceptions\ZgwException`, so you can catch the base class to handle any package error.

| Exception | Thrown when |
|---|---|
| `ApiRequestException` | The provider returned an error status, or a delete did not return 204. Carries the response via `getResponse()`. |
| `ValidationException` | A write was rejected with a structured `ValidatieFout` body. Extends `ApiRequestException`; adds `invalidParams()`, `validationCode()`, `title()`, `detail()`. |
| `AuthorizationException` | `client_id` or `client_secret` is empty when a token is minted. |
| `WeakSecretException` | The `client_secret` does not meet the configured strength rules. |
| `InvalidConfigurationException` | A connection or a base URL is not configured, or the `version` is not a supported ZGW release. |
| `DisallowedHostException` | A `next` link or direct URL targets an origin that is not on the allowlist. |
| `PaginationLimitException` | Auto-pagination exceeded `max_pages`. |
| `InvalidIdentifierException` | A resource identifier contained characters that are not allowed in a URL segment. |

## Testing

The package ships with a PHPUnit suite (Orchestra Testbench). Run it with:

```bash
composer test
```

Coverage reports (a coverage driver such as PCOV or Xdebug is required):

```bash
composer test:coverage        # text summary
composer test:coverage-html   # HTML report in coverage/
```

## License

EUPL-1.2
