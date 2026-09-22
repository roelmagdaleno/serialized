# 0017. Rewrite `S:` escaped strings to `s:` before `unserialize()`

Date: 2026-09-22 · Status: accepted

## Context

PHP writes a string as `S:LEN:"…";` with its bytes spelled as `\XX` escapes, and the tokenizer
already reads and decodes that form. PHP 8.4 deprecated unserializing it, so every payload holding
an `S:` string raised `E_DEPRECATED` from inside `SafeUnserializer`. Under
[ADR 0013](0013-only-warnings-mean-unserialize-failed.md) a deprecation is handed back to PHP, so
the caller saw a notice about a notation the package had deliberately accepted. The suite never
noticed, because the handler around `unserialize()` replaces PHPUnit's rather than chaining to it.

## Decision

Before `unserialize()` runs, `EscapedStringRewriter` re-lexes the validated payload and writes each
`S:` token as the plain `s:` string its escapes spell. A `C:` body is rewritten the same way and
its declared length recalculated, because the class's own `unserialize()` runs inside the same call
and would raise the same deprecation. A payload with no `S:` in it is returned untouched.

## Rejected

- **Swallowing the deprecation in `SafeUnserializer`**, as it already swallows a benign
  out-of-range warning. One line, but it only postpones the problem: once PHP removes the notation,
  every `S:` payload fails.
- **Carrying the validation tokens forward to the rewrite.** This saves a lexing pass, but it ties
  validation to conversion and keeps every token alive until `unserialize()` returns. Only payloads
  that contain `S:` pay for the second pass.

## Consequences

- `S:` payloads convert without a deprecation, and will keep converting after PHP drops the form.
- Nothing unvalidated reaches `unserialize()`: the rewritten bytes are built only from tokens the
  tokenizer and policy have already accepted. A declared `S:` length counts decoded bytes, so no
  rewritten token is longer than the original, and the payload stays within `maxBytes`.
- `R:`/`r:` number values, not bytes, so references still name the same values after the rewrite.
- `ValueNormalizer` still receives the caller's payload, so its diagnostics point at bytes the
  caller sent. Only the rare `RejectedByPhp` diagnostic describes the rewritten bytes PHP actually
  read.
