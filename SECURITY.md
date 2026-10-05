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
   `__PHP_Incomplete_Class` can never reach your code. The one exception is opt-in, and it
   builds nothing: see [Reading objects as data](#reading-objects-as-data).
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

## Reading objects as data

`objectsAsData()` lets a plain `O:` object of a class you have not allowed reach `unserialize()`.
`allowed_classes` stays `false` for that class, so PHP restores the object as a
`__PHP_Incomplete_Class` without loading the class: no autoloader, no
`unserialize_callback_func`, and no `__wakeup()`, `__unserialize()` or `__destruct()`. The package
then turns the incomplete object into a `stdClass` of its properties, from `toArray()` as well as
`toJson()`, so rule 2's promise still holds: an incomplete object never reaches your code.

This mode does not widen anything else:

- `C:` objects and enums still need their class on the allow list.
- A `C:` body never reads objects as data. Every class it names must be allowed, because the
  class that owns the body may unserialize it under its own options.
- A property named `__PHP_Incomplete_Class_Name` is refused. PHP stores the class name under
  that key, so the property would overwrite it.
- An object that contains itself is refused, rather than walked without end.

## Supported versions

The latest minor release receives security fixes.
