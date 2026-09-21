# 0016. `DiagnosticCode` owns the default wording

Date: 2026-09-20 · Status: accepted

## Context

`reason` and `fix` were assembled inside each exception's named constructor. Two problems: a
consumer wanting its own wording — a telemetry label — had to match on English sentences, and
nothing structurally stopped a `reason` and a `fix` from describing different failures.

## Decision

`DiagnosticCode` is a string-backed enum with one case per failure, and it is the only place the
default wording lives. `reason()` and `fix()` are methods on the enum; a `Diagnostic` derives both
from its code in its constructor. Named constructors keep their signatures but now gather the
facts into `context` — `declaredByteLength`, `className`, `configuredLimit` and the rest — as an
`array<string, string|int|bool|null>`, documented per code in `README.md`.

## Consequences

- The code is the stable identifier a consumer matches on: an i18n key, a telemetry label, a
  `match` subject. Wording can change without breaking a consumer.
- `reason` and `fix` cannot disagree, because both come from one case.
- The byte `offset` is never duplicated into `context`; the `Diagnostic` already owns it.
- Adding a failure means adding a case with its two sentences — one edit, in one file.
