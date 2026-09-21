# 0014. Enforce `maxElements` in the tokenizer, and keep offsets in `Token`

Date: 2026-09-19 · Status: accepted

## Context

`maxElements` was checked on the finished token stream. A 13.7 MB payload of four-byte tokens —
comfortably under `maxBytes` — built 4M `Token` objects before anything counted them, peaking at
894 MB and fatally exhausting a 256 MB process. Inside `tryToJson()`, whose whole contract is that
it never throws. A limit that allocates what it exists to refuse is not a limit.

## Decision

The `Tokenizer` counts as it lexes and stops at the ceiling, before the tokens are allocated.
`LimitExceededException::elementCeiling()` reports the configured ceiling rather than a total the
tokenizer deliberately never reaches. `Token` keeps offsets into the payload instead of copies of
its bytes; `raw` and `literal` derive their substring on demand.

## Consequences

- A payload at the byte limit is millions of tokens, so each one's footprint is a design
  constraint, not a micro-optimization. Peak memory fell ~20% on a string-heavy payload, ~11% on
  one of minimal tokens.
- Because a `Token` holds offsets into the whole payload, a `C:` body can be lexed in place and
  still report diagnostics at real offsets (see [ADR 0010](0010-validate-custom-serialized-bodies.md)).
- Each limit is decided in exactly one place: elements where tokens are made, depth where
  structure is known, bytes before either.
- A declared element count the remaining bytes cannot possibly hold is refused at the same point,
  rather than saturating on cast and overflowing to a float.
