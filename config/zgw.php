<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The name of the default connection to use when none is specified.
    |
    */
    'default' => env('ZGW_CONNECTION', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each connection defines the base URLs for each ZGW API and the JWT
    | credentials used for authorization. Multiple connections are supported,
    | following the same pattern as Laravel's database connections.
    |
    | API base URLs should point to the root of the API host so that the package
    | can append the correct path per API, e.g. "https://example.com/".
    |
    */
    'connections' => [

        'main' => [

            /*
            | Base URLs per ZGW API. Each value is the URL up to and including
            | the trailing slash, e.g. "https://openzaak.example.com/".
            */
            'urls' => [
                'zaken' => env('ZGW_ZAKEN_BASE_URL', ''),
                'catalogi' => env('ZGW_CATALOGI_BASE_URL', ''),
                'documenten' => env('ZGW_DOCUMENTEN_BASE_URL', ''),
                'besluiten' => env('ZGW_BESLUITEN_BASE_URL', ''),
            ],

            /*
            | JWT credentials for this connection.
            */
            'client_id' => env('ZGW_CLIENT_ID', ''),
            'client_secret' => env('ZGW_CLIENT_SECRET', ''),
            'user_id' => env('ZGW_USER_ID', ''),
            'user_representation' => env('ZGW_USER_REPRESENTATION', ''),

            /*
            | JWT lifetime in seconds, written to the "exp" claim. ZGW tokens cannot be
            | revoked, so VNG advises a short expiry. Tokens are minted per request, so a
            | small value is sufficient; it only needs to cover one request plus clock skew
            | (the provider applies its own JWT leeway). Set to 0 to omit "exp" entirely for
            | strict legacy providers that reject the claim.
            */
            'jwt_expiry' => (int) env('ZGW_JWT_EXPIRY', 300),

            /*
            | Strength rules for the client_secret, validated when the connection is built.
            | The secret is the HS256 signing key, so a weak secret makes token forgery easier.
            | Any rule omitted here falls back to its default (min_length 32, character classes
            | off). Tighten or relax these per connection: lowering min_length or disabling the
            | character classes relaxes the package's own check.
            |
            | Note: 32 bytes is a hard floor for HS256. firebase/php-jwt 7 refuses to sign with
            | a shorter key, so a secret under 32 bytes cannot be used even with min_length 0.
            | These rules let you relax composition and raise the length, not go below 32 bytes.
            */
            'secret_rules' => [
                'min_length' => (int) env('ZGW_SECRET_MIN_LENGTH', 32),
                'require_uppercase' => env('ZGW_SECRET_REQUIRE_UPPERCASE', false),
                'require_lowercase' => env('ZGW_SECRET_REQUIRE_LOWERCASE', false),
                'require_number' => env('ZGW_SECRET_REQUIRE_NUMBER', false),
                'require_symbol' => env('ZGW_SECRET_REQUIRE_SYMBOL', false),
            ],

            /*
            | Cache store for responses cached via ->cache(). Caching is opt-in; when enabled it
            | stores ZGW response data (which may contain citizen PII) in this store. Leave null
            | to use the application default, or point it at a dedicated or encrypted store to
            | isolate that data. Keep cache TTLs short. Keys are namespaced per client_id so
            | connections with different authorizations never share a cache entry.
            */
            'cache_store' => env('ZGW_CACHE_STORE'),

            /*
            | Content-Crs and Accept-Crs headers sent with every request.
            */
            'accept_crs' => env('ZGW_ACCEPT_CRS', 'EPSG:4326'),
            'content_crs' => env('ZGW_CONTENT_CRS', 'EPSG:4326'),

            /*
            | Host allowlist for following pagination "next" links and direct URL fetches.
            |
            | The base URLs above are always trusted automatically. This list only needs
            | extra entries when a ZGW API returns links to additional hosts (for example a
            | separate document download domain). Each entry may be a full URL or a bare host
            | (a bare host is treated as https). Any request whose origin (scheme, host, port)
            | is not on the resulting allowlist is rejected before the bearer token is sent.
            */
            'allowed_hosts' => [],

            /*
            | HTTP timeouts (in seconds) applied to every request on this connection.
            | connect_timeout bounds the TCP/TLS handshake, timeout bounds the whole request.
            | These protect the calling application from hanging on a slow or hostile upstream.
            */
            'connect_timeout' => (int) env('ZGW_CONNECT_TIMEOUT', 10),
            'timeout' => (int) env('ZGW_TIMEOUT', 30),

            /*
            | Maximum number of pages to follow when auto-paginating a list endpoint.
            | Bounds the "next" link loop so a hostile upstream cannot keep the request
            | running indefinitely. Exceeding this throws a PaginationLimitException.
            */
            'max_pages' => (int) env('ZGW_MAX_PAGES', 1000),

        ],

    ],

];
