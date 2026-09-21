# 0008. Reject non-backed enum cases rather than coercing them

Date: 2026-09-19 · Status: accepted

## Context

A backed enum has a scalar value `json_encode()` can render. A pure enum case has only a name.
Rendering the name would produce output, but it is a value JSON cannot carry, invented by the
package.

## Decision

A non-backed enum case is rejected with `UnrepresentableValueException` and a byte offset, like
every other value JSON cannot carry.

## Consequences

- Consistent with 1.0 rejecting non-UTF-8 strings and non-finite floats outright: the package
  never invents a value the payload did not contain.
- Rendering the case name is coercion, and belongs with the other deferred coercion flags
  (`->withInvalidUtf8Substitute()`, `->withNonFiniteAsNull()`), which are additive.
