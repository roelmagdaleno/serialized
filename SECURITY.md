# Security Policy

## Reporting a vulnerability

Please report security issues privately to **roelmagdaleno@gmail.com** rather than opening a
public issue. You will get an acknowledgement within a few days.

## The security model

This package exists to run `unserialize()` on data you do not trust. Five rules make that safe:

1. **`allowed_classes` is never `true`.** It is `false` when no classes are allowed — the default —
   or the explicit list you passed to `allowClasses()`. No code path passes `true` or omits the
   option.
2. **Objects are rejected before `unserialize()` runs.** The payload is tokenized first; if it
   names a class you have not allowed, it is refused without PHP ever seeing it. A
   `__PHP_Incomplete_Class` can never reach your code.
3. **No `__wakeup()` or `__destruct()` fires for a class you did not name.** Enums and
   custom-serialized objects (`C:`) are governed by the same allow-list as `O:` objects.
4. **Limits are enforced on the token stream**, before memory is allocated for any value. The byte
   limit is checked before the payload is read at all.
5. **The payload is never `eval`'d, included, or written to disk.**

## What allowing a class means

`allowClasses()` is the one place you can trade safety for capability. Allowing a class permits
`unserialize()` to build it and run its `__wakeup()` and `__destruct()` on attacker-controlled
property values. If either method touches the filesystem, the network, or a database, allowing the
class hands that reach to whoever wrote the payload.

Allow classes only for payloads from a source you control.

## Supported versions

The latest minor release receives security fixes.
