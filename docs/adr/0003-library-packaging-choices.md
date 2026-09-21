# 0003. Package as a library: no `version` field, no lock file, no CI

Date: 2026-09-18 · Status: accepted

## Context

Three small packaging questions, one shared answer: this is a library, not an application, and its
quality gate runs locally.

## Decision

- No `version` field in `composer.json`. Packagist derives versions from git tags.
- `composer.lock` is not committed; `.gitignore` carries it. A library must resolve against the
  consuming application's constraints, not pin its own.
- No CI pipeline for 1.0. `composer check` — Pint, PHPStan at level max, the full suite — is run
  locally before any commit, and `composer test -- --coverage --min=100` before a release. A
  `PostToolUse` hook runs Pint and PHPStan on every edited PHP file, and a `Stop` hook runs the
  suite.

## Consequences

- Releasing is `git tag`; there is no second place to bump a number and forget.
- Contributors get no automated check on a pull request. Adding CI is an explicit decision to
  revisit, not a gap to fill silently.
