# 0007. Keep class names out of the JSON output

Date: 2026-09-19 · Status: accepted

## Context

A normalized object loses the name of the class it came from. Adding it back — a `__class` key, or
an envelope like `{"class": …, "properties": …}` — would tell the reader more about the payload.

## Decision

The class name is not emitted. `O:8:"stdClass":0:{}` encodes as `{}`.

## Consequences

- An injected key can collide with a real property; a payload holding a property actually named
  `__class` would be indistinguishable from the package's own annotation.
- An envelope would change the shape of every object in the output, so it cannot be added later
  without a breaking change — but a `->withClassNames()` option is additive and can ship in a
  minor release if anyone asks.
- `{}` for an empty allow-listed object is the documented, correct result.
