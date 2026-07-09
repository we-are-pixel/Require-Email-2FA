# Roadmap / Backlog

This plugin should stay small, stateless, and easy to audit. Items here are
intentionally conservative: they improve maintainability or operational assurance
without expanding the runtime policy surface.

## Backlog

- Add external release monitoring for the GitHub Release zip checksum and, where
  available, its build-provenance attestation. WordPress/Plugin Update Checker
  install the named asset over HTTPS; checksum and attestation verification are
  currently auditable signals, not runtime-enforced checks.
- Keep dependency maintenance boring and regular. Prefer semver-safe updates first
  (for example PHPStan patch releases), and evaluate major PHPUnit/PHPCS upgrades
  separately so the PHP 7.2 runtime-compatibility policy remains clear.
- Preserve the one-file production architecture unless the main plugin file grows
  enough that auditability suffers. If it ever splits, keep security decisions as
  pure functions and keep WordPress glue thin.
