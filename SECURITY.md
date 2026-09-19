# Security Policy

## Reporting a vulnerability

Please report security issues privately to **roelmagdaleno@gmail.com** rather than opening a
public issue. You will get an acknowledgement within a few days.

## The security model

This package exists to run `unserialize()` on data you do not trust. Five rules make that safe:

1. **`allowed_classes` is never `true`.** It is `false` when no classes are allowed — the default —
   or the list you passed to `allowClasses()`. No code path passes `true` or omits the option. The
   list reaches PHP in one normalized spelling, so `'\App\Models\User'` and `'App\Models\User'`
   allow the same class — PHP matches the option case-insensitively but does not strip a leading
   separator, and a package that passed your spelling through would quietly allow neither.
2. **Objects are rejected before `unserialize()` runs.** The payload is tokenized first; if it
   names a class you have not allowed, it is refused without PHP ever seeing it. A
   `__PHP_Incomplete_Class` can never reach your code.
3. **No `__wakeup()` or `__destruct()` fires for a class you did not name.** Enums and
   custom-serialized objects (`C:`) are governed by the same allow-list as `O:` objects, and a
   `C:` body is validated as a payload in its own right — a class it names must be allowed too,
   so allowing one class never widens to whatever its body happens to mention.
4. **Limits are enforced on the token stream**, before memory is allocated for any value, inside a
   `C:` body as well as around it — the body spends the same depth and element budget as the
   payload holding it. The byte limit is checked before the payload is read at all.
5. **The payload is never `eval`'d, included, or written to disk.**

## What allowing a class means

`allowClasses()` is the one place you can trade safety for capability. Allowing a class permits
`unserialize()` to build it and run its `__wakeup()` and `__destruct()` on attacker-controlled
property values. If either method touches the filesystem, the network, or a database, allowing the
class hands that reach to whoever wrote the payload.

Allow classes only for payloads from a source you control, and never build the list from input the
payload's author can influence — `allowClasses()` is the one decision the package takes on trust.

A `C:` body is validated before `unserialize()` sees it, but what the class then does with those
bytes is the class's own business: one whose `unserialize()` re-enters `unserialize()` with
`allowed_classes => true` is choosing to ignore the restriction, and no caller-side check can stop
it.

## Supported versions

The latest minor release receives security fixes.
