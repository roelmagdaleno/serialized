# 0004. `tryToJson()` catches `Throwable`, not just `SerializedException`

Date: 2026-09-18 · Status: accepted

## Context

`tryToJson()` promises a `?string` and that it never throws. Catching only the package's own
exception hierarchy would honour that promise for every failure the package anticipated, and break
it for every one it did not — an `Error` from memory exhaustion, an extension warning promoted to
an exception, a raw `TypeError` from an integer overflow.

## Decision

`tryToJson()` catches `Throwable` and returns `null`. `toJson()` remains the throwing path for
callers who want the diagnostic.

## Consequences

- The contract holds under conditions the package did not foresee, which is exactly when a caller
  relying on "never throws" is least able to cope.
- A bug inside the package can hide as a `null` here. The suite therefore exercises failures
  through `toJson()`, where the exception is visible, and uses `tryToJson()` only to assert the
  contract itself.
