# 0015. Allowing a class is honoured only if PHP can actually restore it

Date: 2026-09-19 · Status: accepted

## Context

`allowClasses()` records the caller's intent, not PHP's capability. Allowing a name PHP cannot
autoload yields a `__PHP_Incomplete_Class` — not the class the caller vouched for — carrying the
pseudo-property `__PHP_Incomplete_Class_Name` into the output. Allowing an abstract class, an
interface, a trait, or an enum written as an object token makes `unserialize()` raise a raw
`Error`, escaping the package's exception hierarchy.

## Decision

`ClassRestorability` decides, before `unserialize()` runs, whether PHP can rebuild each allowed
class that the payload actually names. One that it cannot is refused with
`UnsafeSerializedDataException` naming the class and its offset. An enum reached through an `E:`
token is the one restorable-but-not-instantiable case, and is checked with `enum_exists()`.

## Consequences

- `__PHP_Incomplete_Class` can never reach the caller, which is what the security model promises.
- Every failure leaves through the package's own exception hierarchy.
- The check runs against the classes the payload names, not the whole allow-list, so allowing a
  class that never appears costs nothing.
