# 0018. Read objects of unlisted classes as data, on request

Date: 2026-10-05 · Status: accepted

## Context

A converter that shows "what is in this payload" refuses the most common thing a payload holds:
an object. Most callers do not want the object, only its properties, and cannot allow-list the
class. It belongs to another application, or it should never be built in this process at all.

Called with `allowed_classes => false`, PHP restores an object of any class as a
`__PHP_Incomplete_Class` that carries every property. It does not autoload the class, call
`unserialize_callback_func`, or run `__wakeup()`, `__unserialize()` or `__destruct()`, even when a
class of that name exists. That makes the incomplete object a safe carrier for the data. The
danger is the class, not the bytes.

## Decision

`objectsAsData()` lets an `O:` object of a class that is not on the allow list through
`PayloadPolicy`. `allowed_classes` stays `false` for it, so PHP builds an incomplete object, and
`ValueNormalizer` turns that object into a `stdClass` of its properties. Private and protected
names are qualified with the class name the payload gave, not `__PHP_Incomplete_Class`.

- **The option is off by default.** Default output and default refusals are unchanged.
- **`toArray()` normalizes too in this mode.** Every object comes back as a `stdClass`, so an
  incomplete object never reaches the caller. Its properties cannot even be read without
  converting it, and an incomplete object nested inside an allowed class could not be swapped out
  without touching that object.
- **`C:` and `E:` are still governed by the allow list.** PHP drops a `C:` body when its class is
  not allowed, so its data would come back empty. An enum is looked up whatever `allowed_classes`
  says, so it is a class decision, not data.
- **A `C:` body never reads objects as data.** An allowed custom class may call `unserialize()` on
  its own body with its own options. Every class the body names must therefore still be allowed,
  as [0010](0010-validate-custom-serialized-bodies.md) requires.
- **The marker property name is refused.** A property named `__PHP_Incomplete_Class_Name`
  overwrites the class name PHP stores under that same key, so it is refused with
  `ReservedPropertyName` rather than lost.

## Rejected alternatives

- **Telling callers to allow-list every class.** This runs each class's magic methods on data the
  payload controls, which is the attack the package exists to stop.
- **Returning the `__PHP_Incomplete_Class` from `toArray()`.** It breaks the promise in
  [0015](0015-check-class-restorability.md) that an incomplete class never reaches the caller,
  and hands back a value whose properties PHP will not let anyone read.
- **Parsing objects into arrays in the tokenizer.** The tokenizer is a gatekeeper and never
  produces values ([0001](0001-tokenize-before-unserialize.md)). `unserialize()` already builds
  the value safely.

## Consequences

- One option converts payloads from any application without trusting any of its classes.
- The class name is still left out of the output ([0007](0007-omit-class-names-from-output.md)).
- Normalizing objects means walking them, so an object that contains itself (`r:` back to an
  ancestor) is refused as `ContainsReference`. Without that check the walk recursed until the
  process crashed. The crash was also reachable for an allow-listed class.
