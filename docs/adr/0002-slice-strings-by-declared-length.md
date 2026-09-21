# 0002. Slice strings by declared byte length, never by scanning

Date: 2026-09-18 · Status: accepted

## Context

A serialized string is written `s:<bytes>:"<value>";`. The value may contain `"`, `;`, `}`, a NUL
byte, or anything else — `s:5:"a";b";` is a legal string whose value is `a";b`. Scanning forward
for the closing `";` is the single most common way a hand-written serialized parser gets this
wrong, and it is both a correctness and a security bug: the scan ends early, the remaining bytes
are re-lexed as structure, and the tokenizer's view of the payload stops matching PHP's.

## Decision

The tokenizer reads the declared byte count and slices exactly that many bytes, then asserts that
the closing `";` sits where the count says it does. It never searches for a delimiter. Length is
counted in **bytes**, so `mb_*` functions are banned inside the tokenizer — `strlen` and `substr`
only.

## Consequences

- `s:5:"a";b";` and multibyte strings tokenize correctly, and both are permanent test cases.
- A wrong declared length is caught as a length mismatch with a byte-accurate offset, rather than
  silently reinterpreting the payload.
- Anyone adding a token type must follow the same rule: read the declared length, never scan.
