<?php

declare(strict_types=1);

namespace Woweb\Zgw\Connection;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\AutorisatiesApi;
use Woweb\Zgw\Api\BesluitenApi;
use Woweb\Zgw\Api\CatalogiApi;
use Woweb\Zgw\Api\DocumentenApi;
use Woweb\Zgw\Api\NotificatiesApi;
use Woweb\Zgw\Api\ZakenApi;
use Woweb\Zgw\Auth\ClientSecretValidator;
use Woweb\Zgw\Contracts\AuthorizationInterface;
use Woweb\Zgw\Enums\ZgwVersion;
use Woweb\Zgw\Exceptions\DisallowedHostException;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

readonly class ZgwConnection
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly AuthorizationInterface $authorization,
    ) {
        // Fail fast on a weak signing secret before any request can be made with it.
        (new ClientSecretValidator)->validate(
            (string) ($config['client_secret'] ?? ''),
            $config['secret_rules'] ?? [],
        );
    }

    /**
     * Return the configured base URL for the given API name.
     *
     * The value is the full base URL of the API, including the version path, e.g.
     * "https://openzaak.example.com/zaken/api/v1/" or "https://zaken-api.example.com/api/v1/".
     * A trailing slash is ensured; no path is appended, so any deployment topology is supported.
     *
     * Throws if the base URL is not configured.
     */
    public function getBaseUrl(string $api): string
    {
        /** @var array<string, string> $urls */
        $urls = $this->config['urls'] ?? [];
        $base = $urls[$api] ?? '';

        if ($base === '') {
            throw new InvalidConfigurationException(
                "ZGW connection base URL for API [{$api}] is not configured (config key: [urls.{$api}])."
            );
        }

        return rtrim($base, '/').'/';
    }

    /**
     * The ZGW standard release this connection targets (defaults to the latest supported).
     */
    public function getVersion(): ZgwVersion
    {
        $version = $this->config['version'] ?? null;

        return $version === null
            ? ZgwVersion::latest()
            : ZgwVersion::fromConfig((string) $version);
    }

    /**
     * Build the HTTP headers for every request on this connection.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Authorization' => $this->authorization->getToken($this->config),
            'Accept-Crs' => $this->config['accept_crs'] ?? 'EPSG:4326',
            'Content-Crs' => $this->config['content_crs'] ?? 'EPSG:4326',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Assert that the given URL targets an origin on this connection's allowlist.
     *
     * Used before following a pagination "next" link or fetching a direct URL, so the
     * Authorization bearer token is never sent to an untrusted host (SSRF / credential leakage).
     *
     * @throws DisallowedHostException
     */
    public function assertUrlAllowed(string $url): void
    {
        if (! $this->isUrlAllowed($url)) {
            $origin = $this->normalizeOrigin($url) ?? 'an unparseable URL';

            throw new DisallowedHostException(
                "Refusing to send a ZGW request to [{$origin}]: the origin is not on the connection allowlist. ".
                'Add it to the connection [urls] or [allowed_hosts] config if it is trusted.'
            );
        }
    }

    /**
     * Whether the given URL targets an allowed origin (scheme + host + port).
     */
    public function isUrlAllowed(string $url): bool
    {
        $origin = $this->normalizeOrigin($url);

        if ($origin === null) {
            return false;
        }

        return in_array($origin, $this->allowedOrigins(), true);
    }

    /**
     * The set of allowed origins for this connection.
     *
     * By default this contains every configured base URL (the four ZGW APIs). It can be
     * extended with an optional [allowed_hosts] config key (an array of URLs or bare hosts).
     *
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        /** @var array<string, string> $urls */
        $urls = $this->config['urls'] ?? [];
        /** @var array<int, string> $extra */
        $extra = $this->config['allowed_hosts'] ?? [];

        $origins = [];

        foreach ([...array_values($urls), ...array_values($extra)] as $candidate) {
            $origin = $this->normalizeOrigin($candidate, assumeScheme: true);

            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * Reduce a URL to a normalized "scheme://host:port" origin for strict comparison.
     *
     * Returns null when the value cannot be parsed into a host. When $assumeScheme is true
     * (used for allowlist config entries) a missing scheme defaults to https, so a bare host
     * such as "example.com" is treated as "https://example.com:443".
     */
    private function normalizeOrigin(string $url, bool $assumeScheme = false): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($assumeScheme && ! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        $defaultPort = match ($scheme) {
            'https' => 443,
            'http' => 80,
            default => null,
        };

        $port = $parts['port'] ?? $defaultPort;

        if ($port === null) {
            return null;
        }

        return $scheme.'://'.$host.':'.$port;
    }

    /**
     * Build a pre-configured HTTP client for this connection.
     *
     * Every request carries the authorization headers and the configured connect/request
     * timeouts, so no call site can accidentally issue an unauthenticated or unbounded request.
     */
    public function request(): PendingRequest
    {
        return Http::withHeaders($this->getHeaders())
            ->connectTimeout($this->getConnectTimeout())
            ->timeout($this->getTimeout());
    }

    /**
     * Connection timeout (seconds) for the TCP/TLS handshake.
     */
    public function getConnectTimeout(): int
    {
        return (int) ($this->config['connect_timeout'] ?? 10);
    }

    /**
     * Total request timeout (seconds).
     */
    public function getTimeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    /**
     * Maximum number of pages to follow when auto-paginating.
     */
    public function getMaxPages(): int
    {
        return (int) ($this->config['max_pages'] ?? 1000);
    }

    /**
     * The cache store to use for cached responses, or null for the application default.
     * Point this at a dedicated or encrypted store to isolate cached ZGW data (PII).
     */
    public function getCacheStore(): ?string
    {
        $store = $this->config['cache_store'] ?? null;

        return $store === null ? null : (string) $store;
    }

    /**
     * A stable per-credential namespace for cache keys.
     *
     * Cached responses are scoped to the client_id so two connections to the same host with
     * different credentials (and therefore different authorizations) never share a cache entry.
     */
    public function getCacheNamespace(): string
    {
        return (string) ($this->config['client_id'] ?? '');
    }

    public function zaken(): ZakenApi
    {
        return new ZakenApi($this);
    }

    public function catalogi(): CatalogiApi
    {
        return new CatalogiApi($this);
    }

    public function documenten(): DocumentenApi
    {
        return new DocumentenApi($this);
    }

    public function besluiten(): BesluitenApi
    {
        return new BesluitenApi($this);
    }

    public function autorisaties(): AutorisatiesApi
    {
        return new AutorisatiesApi($this);
    }

    public function notificaties(): NotificatiesApi
    {
        return new NotificatiesApi($this);
    }
}
