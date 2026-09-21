# 0006. Qualify colliding property names as `Class::name`, never drop one

Date: 2026-09-19 · Status: accepted

## Context

A child class redeclaring a parent's private property yields two entries, `\0Parent\0x` and
`\0Child\0x`, which demangle to the same name. Writing both into one `stdClass` means one
overwrites the other, and whichever loses disappears with no error — the exact silent loss the
normalization stage exists to prevent.

## Decision

When two or more properties demangle to one name, every member of that collision is written as
`Class::name` — `Parent::x` and `Child::x` — with no bare `x` in the output.

## Consequences

- No value is ever lost, and the output says plainly which class each one came from.
- A consumer reading the JSON must tolerate a `::` in a key for this case. It occurs only on a
  genuine collision; a non-colliding private property keeps its bare name.
- Picking a winner — parent or child, first or last — was rejected: any rule silently discards a
  value the payload contained.
