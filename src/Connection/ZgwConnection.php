<?php

declare(strict_types=1);

namespace Woweb\Zgw\Connection;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Woweb\Zgw\Api\AutorisatiesApi;
use Woweb\Zgw\Api\BesluitenApi;
use Woweb\Zgw\Api\CatalogiApi;
use Woweb\Zgw\Api\DocumentenApi;
use Woweb\Zgw\Api\NotificatiesApi;
use Woweb\Zgw\Api\ZakenApi;
use Woweb\Zgw\Auth\ClientSecretValidator;
use Woweb\Zgw\Contracts\AuthorizationInterface;
use Woweb\Zgw\Enums\ZgwVersion;
use Woweb\Zgw\Events\ZgwRequestSent;
use Woweb\Zgw\Exceptions\DisallowedHostException;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

readonly class ZgwConnection
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly AuthorizationInterface $authorization,
        private readonly string $name = '',
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
        $request = Http::withHeaders($this->getHeaders())
            ->connectTimeout($this->getConnectTimeout())
            ->timeout($this->getTimeout());

        return $this->applyRetry($this->withAuditTrail($request));
    }

    /**
     * The configured name of this connection (empty when built outside the manager).
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Attach a middleware that emits a ZgwRequestSent event for every response received.
     *
     * The event is a seam for request-level audit logging; with no listeners it costs nothing.
     * It fires per HTTP exchange, so each retry attempt emits its own event.
     */
    private function withAuditTrail(PendingRequest $request): PendingRequest
    {
        return $request->withMiddleware(function (callable $handler): callable {
            /**
             * @param  array<string, mixed>  $options
             */
            return function (RequestInterface $psrRequest, array $options) use ($handler): PromiseInterface {
                return $handler($psrRequest, $options)->then(
                    function (ResponseInterface $response) use ($psrRequest): ResponseInterface {
                        Event::dispatch(new ZgwRequestSent(
                            $this->name,
                            $this->getCacheNamespace(),
                            $psrRequest->getMethod(),
                            (string) $psrRequest->getUri(),
                            $response->getStatusCode(),
                        ));

                        return $response;
                    }
                );
            };
        });
    }

    /**
     * Apply the connection's transient-failure retry policy, if configured.
     *
     * Retries are opt-in (retry_times defaults to 0). Only idempotent methods are retried, and
     * only on a connection error, a 429, or a 5xx response. The final attempt's response is
     * returned as-is (throw: false), so a genuine failure still surfaces through the package's
     * own response validation rather than a raw Laravel exception.
     */
    private function applyRetry(PendingRequest $request): PendingRequest
    {
        $times = $this->getRetryTimes();

        if ($times < 1) {
            return $request;
        }

        return $request->retry(
            $times + 1,
            $this->retrySleepCallback(),
            $this->retryDecisionCallback(),
            throw: false,
        );
    }

    /**
     * Decide whether a failed attempt should be retried.
     *
     * Only idempotent methods (GET, HEAD, PUT, DELETE) are eligible, so a create or update is
     * never silently repeated. A missing status (a connection-level error) is treated as transient.
     *
     * @return callable(Throwable, PendingRequest, ?string): bool
     */
    private function retryDecisionCallback(): callable
    {
        return function (Throwable $exception, PendingRequest $request, ?string $method): bool {
            if (! in_array(strtoupper((string) $method), ['GET', 'HEAD', 'PUT', 'DELETE'], true)) {
                return false;
            }

            $status = $exception instanceof RequestException ? $exception->response->status() : null;

            if ($status === null) {
                return true;
            }

            return $status === 429 || $status >= 500;
        };
    }

    /**
     * The wait (in milliseconds) before the next attempt.
     *
     * Honours a Retry-After header when present (seconds or an HTTP date), otherwise backs off
     * exponentially from retry_sleep_ms. The result is capped at retry_max_sleep_ms either way.
     *
     * @return Closure(int, mixed): int
     */
    private function retrySleepCallback(): Closure
    {
        $base = $this->getRetrySleepMs();
        $max = $this->getRetryMaxSleepMs();

        return function (int $attempt, mixed $exception) use ($base, $max): int {
            $retryAfter = $this->retryAfterMs($exception);

            if ($retryAfter !== null) {
                return min($retryAfter, $max);
            }

            return (int) min($base * (2 ** ($attempt - 1)), $max);
        };
    }

    /**
     * Parse a Retry-After header from the failed response into milliseconds, or null when absent.
     *
     * Supports both forms allowed by the HTTP spec: a delay in seconds and an HTTP date.
     */
    private function retryAfterMs(mixed $exception): ?int
    {
        if (! $exception instanceof RequestException) {
            return null;
        }

        $header = trim($exception->response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header * 1000;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time()) * 1000;
    }

    /**
     * Number of extra attempts after the first for transient failures (0 disables retries).
     */
    public function getRetryTimes(): int
    {
        return max(0, (int) ($this->config['retry_times'] ?? 0));
    }

    /**
     * Base backoff (milliseconds) between retry attempts.
     */
    public function getRetrySleepMs(): int
    {
        return max(0, (int) ($this->config['retry_sleep_ms'] ?? 100));
    }

    /**
     * Maximum backoff (milliseconds) between retry attempts; also caps a Retry-After wait.
     */
    public function getRetryMaxSleepMs(): int
    {
        return max(0, (int) ($this->config['retry_max_sleep_ms'] ?? 5000));
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
