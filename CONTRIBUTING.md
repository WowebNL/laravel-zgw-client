# Contributing

Thanks for considering a contribution. This package aims to be a small, strict, well tested
client for the ZGW APIs, so contributions are reviewed with that in mind.

## Before you start

For anything larger than a bug fix, please open an issue first to discuss the approach. This
avoids work that does not fit the scope (a thin, version aware transport client plus an opt-in
typed layer).

For security issues, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Development setup

```bash
composer install
```

The package targets PHP 8.2 and Laravel 11 through 13. Code uses `declare(strict_types=1)`
everywhere and is analysed at PHPStan level 8.

## Quality gates

All of these must pass before a pull request can be merged. They also run in CI.

```bash
composer test          # unit and integration suites
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/pint        # code style (run without --test to autofix)
composer contract      # fetch the OpenAPI specs and run the contract suite
```

The contract suite validates the client against the pinned OpenAPI specs for ZGW 1.5, 1.6 and
1.7. If you add or change an endpoint, the coverage and version availability tests will tell
you when the client and the specs disagree.

## Conventions

1. Match the surrounding code: naming, structure, and comment density.
2. Comments and documentation are written in English.
3. Keep public behaviour backward compatible unless a change is discussed and noted in the
   changelog.
4. Add tests for new behaviour. Mock HTTP with Laravel's `Http::fake()`, as the existing
   integration tests do.
5. Update [CHANGELOG.md](CHANGELOG.md) under the unreleased section.

## Commit and pull request messages

Write commit messages and pull request descriptions in English, and describe the why, not only
the what.
