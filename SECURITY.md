# Security Policy

This package is used to talk to Dutch government registries over the ZGW APIs, so it handles
authorization secrets and citizen data. Security reports are taken seriously.

## Supported versions

Security fixes are provided for the latest released minor version. Until the first tagged
release, fixes land on the default branch.

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately so it can be
fixed before it becomes widely known.

1. Email info@woweb.nl with a description of the issue.
2. Include the affected version or commit, reproduction steps, and the impact you foresee.
3. If you have a proposed fix, you are welcome to attach it.

You can expect an acknowledgement within five working days. Once the issue is confirmed, a fix
and a coordinated disclosure timeline will be agreed with you.

## Scope and design notes

A few security properties are intentional and documented in the README:

1. Authorization uses HS256 (a shared secret). Asymmetric signing (RS256) is out of scope.
2. The client secret is validated for strength when a connection is built. The 32 byte floor
   for HS256 cannot be lowered.
3. Outbound requests are restricted to an allowlist of origins, so a bearer token is never sent
   to an untrusted host when following a pagination link or resolving a URL.
4. Resource identifiers placed in URLs are validated and encoded.
5. Cached responses are namespaced per credential, so connections never share a cache entry.

If you find a way around any of these, that qualifies as a vulnerability worth reporting.
