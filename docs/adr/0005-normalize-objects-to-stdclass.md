# 0005. Normalize objects to `stdClass` before encoding

Date: 2026-09-19 · Status: accepted

## Context

`json_encode()` serializes an object's **public** properties and silently drops the rest. An
allow-listed `Money` with a private `$amount` encodes as `{}` — the package's central promise,
"show me what is in this payload", answered with an empty object and no error.

Casting the object to an array exposes every property, but PHP writes private and protected names
with NUL delimiters (`\0Class\0name`, `\0*\0name`), and an array whose keys are all numeric
encodes as a JSON *array*, losing the object/array distinction the payload drew.

## Decision

A `ValueNormalizer` stage runs between `SafeUnserializer` and `JsonEncoder` on the JSON path only.
An object becomes a `stdClass` — never an array — with property names demangled by `PropertyName`.
Arrays are walked recursively; scalars pass through; enums are passed through untouched, because
`json_encode()` already renders a backed enum as its value and casting one would turn `"h"` into
`{"name": "h", "value": "h"}`.

## Consequences

- Private and protected properties appear in the output.
- An object whose property names are all numeric stays a JSON object.
- `toArray()` is unaffected: it stops before this stage and returns the real object graph.
- Recursion terminates only because a cycle can be serialized solely as `r:`/`R:`, which
  `PayloadPolicy` rejects wherever it appears, including inside a `C:` body. Weakening that
  rejection reintroduces a stack overflow here.
