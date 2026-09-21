# 0012. Reject a float by its value, not by its spelling

Date: 2026-09-19 · Status: accepted

## Context

JSON has no representation for `NAN` or infinity. The original check matched the literals `NAN`,
`INF` and `-INF`, which misses every literal that *overflows* to infinity on parsing — `d:1e999;`,
`d:1e309;`. Those reached `json_encode()` and surfaced as `JsonEncodingException`, whose
`diagnostic()` is null, so the caller got no offset and no fix.

## Decision

`JsonRepresentability` rejects a float literal when the parsed value is not finite, whatever its
spelling.

## Consequences

- Every non-finite float is caught in the policy stage with a byte-accurate diagnostic.
- `JsonEncodingException` returns to being unreachable in practice, which is what its "should
  never happen" status claims.
- A check on the value rather than the text cannot be outgrown by a new spelling.
