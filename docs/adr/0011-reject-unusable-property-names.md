# 0011. Reject property names that do not survive demangling

Date: 2026-09-19 · Status: accepted

## Context

PHP stores a private property as `\0Class\0name` and a protected one as `\0*\0name`. A payload can
also declare a name that is neither — `"\0"` alone, `"\0ab"`, `"\0A\0b\0c"` — and still holds a NUL
after demangling. Such a name cannot be assigned to a `stdClass`: it threw
`Error: Cannot access property starting with "\0"` straight out of `toJson()`, outside the
package's exception hierarchy. Building the object from an array instead would have made
`json_encode()` drop the property without a word.

## Decision

The rule lives in one value object, `PropertyName`. The normalizer asks it to split a storage key;
`PayloadPolicy` asks it whether the split survives, and refuses the payload with
`UnrepresentableValueException` and the name's byte offset **before** `unserialize()` runs. The
parser records only those property names that hold a NUL byte, so the check costs nothing on
ordinary payloads.

## Consequences

- The failure is a typed package exception with an offset, not a raw `Error`.
- Neither silent loss nor a crash: the payload is named as unrepresentable, which it is.
- Two stages share one rule and cannot disagree about what a mangled name means.
- A NUL inside an array *key* or a string *value* is untouched — only property names are affected.
