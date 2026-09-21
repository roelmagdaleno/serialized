# 0009. Pass a normalized allow-list spelling to `unserialize()`

Date: 2026-09-19 · Status: accepted

## Context

PHP matches `allowed_classes` case-insensitively but does **not** strip a leading separator. An
entry spelled `'\App\Models\User'` therefore matches nothing, while the package believes the class
is allowed. `unserialize()` refuses it after the fact, and the caller receives a
`__PHP_Incomplete_Class` carrying `__PHP_Incomplete_Class_Name` — precisely what the security
model promises cannot happen.

## Decision

`ClassAllowList` normalizes the caller's entries once, and the normalized spelling is what reaches
`unserialize()`. The caller's own spelling is never passed through.

## Consequences

- `'\Money'`, `'Money'`, `'MONEY'` and `'\MONEY'` all allow the same class, and the package's view
  of what is allowed matches PHP's.
- The allowed-class decision lives in exactly one class; nothing else compares class names.
