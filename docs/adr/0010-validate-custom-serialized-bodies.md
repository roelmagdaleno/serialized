# 0010. Validate a `C:` body as a payload in its own right

Date: 2026-09-19 · Status: accepted

## Context

A custom-serialized object, `C:<len>:"<class>":<len>:{<body>}`, hands its body to the class's own
`unserialize()`. Treating the body as opaque bytes — the original behaviour — let it smuggle an
`R:`/`r:` reference past the policy, producing a cyclic value that exhausted the stack in
`ValueNormalizer`; name classes the allow-list never saw; bypass `maxDepth` and `maxElements`; and
carry non-UTF-8 strings and `NAN` that surfaced as a `JsonEncodingException` with no offset.

## Decision

The body is lexed and validated in place, at its real offsets in the payload, by the same three
stages. Its depth and element count are spent from the same budget as the payload around it. A
body that does not tokenize as one complete serialized value is refused.

## Consequences

- Every guarantee that holds around a `C:` token holds inside it, and one limit cannot be split
  across a nesting boundary.
- A diagnostic raised inside a body still points at a byte of the payload the caller passed, which
  is why the tokenizer lexes a byte range rather than a copied substring.
- The package cannot vouch for bytes it cannot read, so an unreadable body is refused rather than
  passed to the class.
- What an allowed class then does with a validated body is its own business: one whose
  `unserialize()` re-enters `unserialize()` with `allowed_classes => true` is opting out, and no
  caller-side check can stop it. `SECURITY.md` says so plainly.
