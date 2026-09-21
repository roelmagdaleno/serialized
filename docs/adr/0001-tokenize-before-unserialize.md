# 0001. Tokenize and validate before calling `unserialize()`

Date: 2026-09-18 · Status: accepted

## Context

`unserialize()` is the only correct way to rebuild a PHP value, but it is unsafe on untrusted
input and it reports failure as `Error at offset N of M bytes` — no reason, no fix. Two options
were on the table: call `unserialize()` and improve the message afterwards, or write a parser and
drop `unserialize()` entirely.

## Decision

Neither. A `Tokenizer` lexes the payload first and decides whether `unserialize()` may be called;
`unserialize()` still produces the value. The tokenizer is a gatekeeper, not a value producer, and
`ParsedPayload` carries only metadata — depth, element counts, class names, offsets — never values.

## Consequences

- Diagnostics can name the token type, the declared length and the actual length, which is what
  turns "offset 24" into "declared 6 bytes, found 5 — change `s:6` to `s:5`".
- Objects, references and over-limit payloads are refused before PHP allocates anything for them.
- The package never reimplements PHP's deserialization, so it cannot drift from PHP's semantics in
  the value it returns.
- It *can* drift in what it accepts. Every rejection test therefore asserts that `unserialize()`
  also fails on that payload, and every acceptance test round-trips real `serialize()` output.
- Once a tokenizer exists it is tempting to have it produce values and drop `unserialize()`. That
  is the scope creep this decision exists to forbid.
