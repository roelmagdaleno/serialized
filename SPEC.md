# Spec: roelmagdaleno/serialized

> Status: **approved** (2026-09-18) · Phase 1 (Specify) of spec-driven development.
> Update this document before changing the code it describes.
>
> Amended and approved (2026-09-19): the `ValueNormalizer` stage, and the restorability check on
> allow-listed classes.
>
> Amended and approved (2026-09-19): the allow-list is normalized once and the normalized spelling
> is what reaches `unserialize()`; a custom-serialized (`C:`) body is validated as a nested payload
> rather than passed through opaque.
>
> Amended and approved (2026-09-19): a property name that does not survive demangling is rejected
> before `unserialize()`, and the mangled-name rule lives in one `PropertyName` value object.
>
> Amended and approved (2026-09-19): a float literal is rejected when its value is non-finite, not
> only when it is spelled `NAN`/`INF`; a PHP diagnostic that is not a failure no longer rejects the
> payload.
>
> Amended and approved (2026-09-19): `maxElements` is enforced by the `Tokenizer` as it lexes, and
> a declared element count is refused when the bytes left cannot hold it. `Token` keeps offsets
> into the payload instead of copies of its bytes, since a payload at the byte limit is millions
> of them.

## Objective

A framework-agnostic PHP 8.4 package, published on Packagist as `roelmagdaleno/serialized`,
that converts PHP serialized data into pretty-printed JSON — safely, and with error messages
good enough to act on.

**Users**

1. [unserialize.dev](https://unserialize.dev) (Laravel) — pastes arbitrary, untrusted payloads
   from the public internet and needs to show the user *what* is wrong and *how* to fix it.
2. Other PHP developers debugging WordPress meta, legacy session blobs, queue payloads, and
   database columns that hold serialized data.

**Success looks like**: given the payload below, `Serialized::toJson($payload)` returns the JSON
below — and given a broken payload, the thrown exception tells the user the byte offset, the
reason, and a concrete fix.

```php
$payload = 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0"; … }';

echo Serialized::toJson($payload);
```

```json
{
    "name": "Chrome",
    "version": "103.0.0.0",
    "platform": "Windows",
    "update_url": "https://www.google.com/chrome",
    "img_src": "https://s.w.org/images/browsers/chrome.png?1",
    "img_src_ssl": "https://s.w.org/images/browsers/chrome.png?1",
    "current_version": "18",
    "upgrade": false,
    "insecure": false,
    "mobile": false
}
```

## Tech Stack

| Concern | Choice |
|---|---|
| Language | PHP `^8.4` |
| Runtime dependencies | **None.** `ext-json` and `ext-mbstring` only |
| Framework coupling | None. No `illuminate/*` in `require` or `require-dev` |
| Tests | Pest `^5.2` |
| Formatting | Laravel Pint `^1.32` |
| Static analysis | PHPStan `^2.2`, level `max`, `src/` and `tests/` |
| License | MIT |
| Versioning | SemVer, derived from git tags. No `version` field in `composer.json` |
| CI | None for 1.0. `composer check` is the gate, run locally |

## Commands

```bash
composer install                  # install dev dependencies
composer test                     # vendor/bin/pest
composer coverage                 # pest --coverage --min=100 (loads Xdebug explicitly)
composer pint                     # vendor/bin/pint        (fix formatting)
composer pint -- --test           # vendor/bin/pint --test (check only, used in CI)
composer stan                     # vendor/bin/phpstan analyse
composer check                    # pint --test && stan && test — the full gate
```

All of these are defined in `composer.json`. There is no CI pipeline for 1.0 — `composer check`
is run locally before any commit, and `composer coverage` before a release.

## Public API

Two entry points, one shared engine. The static class is a thin delegator — all behaviour
lives in `SerializedConverter`, which is immutable and fluent.

```php
use Serialized\Serialized;

// The 90% case
$json = Serialized::toJson($payload);

// Non-throwing variant for callers that only care whether it worked
$json = Serialized::tryToJson($payload);   // ?string, null on any failure

// Other output formats
$value = Serialized::toArray($payload);    // mixed — the unserialized PHP value
$ok    = Serialized::isValid($payload);    // bool — validation only, no conversion

// The configured case
$json = Serialized::make()
    ->withMaxBytes(1_000_000)
    ->withMaxDepth(32)
    ->withMaxElements(50_000)
    ->allowClasses([Money::class])
    ->compact()                            // drop JSON_PRETTY_PRINT
    ->withJsonFlags(JSON_NUMERIC_CHECK)    // additive, on top of the defaults
    ->toJson($payload);
```

`Serialized::make()` returns a `SerializedConverter` with default `Options`.
Every `with*`/`allow*`/`compact`/`pretty` method returns a **new** instance (`readonly` clone),
so a configured converter is safe to share, cache, and bind in a container.

`SerializedConverter` exposes the same four verbs as the static class: `toJson`, `tryToJson`,
`toArray`, `isValid`.

### Defaults

| Option | Default | Why |
|---|---|---|
| `maxBytes` | `16 * 1024 * 1024` (16 MB) | Above any realistic WP/Laravel payload, below memory exhaustion |
| `maxDepth` | `64` | Guards the stack-overflow vector |
| `maxElements` | `1_000_000` | Guards the "billion laughs" style expansion |
| `allowedClasses` | `[]` (none) | Object instantiation is the attack vector |
| JSON flags | `JSON_PRETTY_PRINT \| JSON_UNESCAPED_SLASHES \| JSON_UNESCAPED_UNICODE \| JSON_THROW_ON_ERROR` | Matches the target output above |

## Conversion Pipeline

Each stage is one class with one job. No stage knows about the stage after it.

```
  string $payload
        │
        ▼
┌───────────────────┐
│ Tokenizer         │  payload → Token[] (type, offset, length, raw)
│                   │  catches: truncated tokens, bad length prefixes,
│                   │           unknown type letters, trailing bytes,
│                   │           element counts the remaining bytes cannot hold
│                   │  lexes a byte range, so a C: body is lexed in place
│                   │  stops at maxElements, before the tokens are allocated
└───────────────────┘
        │  Token[]
        ▼
┌───────────────────┐
│ Parser            │  Token[] → structural validation
│                   │  catches: unbalanced braces, wrong element counts,
│                   │           non-scalar array keys
└───────────────────┘
        │  ParsedPayload (depth, elementCount, classNames, flags)
        ▼
┌───────────────────┐
│ PayloadPolicy     │  applies Options against ParsedPayload
│                   │  rejects: disallowed classes, references (R:/r:),
│                   │           non-UTF-8 strings, NAN/INF floats, depth
└───────────────────┘
        │  (validated)
        ▼
┌───────────────────┐
│ SafeUnserializer  │  unserialize($payload, ['allowed_classes' => …])
│                   │  wraps warnings as exceptions; never returns false silently
│                   │  a notice or deprecation is left to PHP, not read as failure
└───────────────────┘
        │  mixed
        ▼
┌───────────────────┐
│ ValueNormalizer   │  objects → stdClass with demangled property names
│                   │  so private and protected properties survive encoding
└───────────────────┘
        │  mixed
        ▼
┌───────────────────┐
│ JsonEncoder       │  json_encode($value, $flags)
└───────────────────┘
        │
        ▼
    string $json
```

**Why tokenize before calling `unserialize()`:** `unserialize()` reports only
`Error at offset N of M bytes` — no reason, no fix. The tokenizer knows the token type, the
declared length, and the actual length, which is what turns "offset 24" into
"declared 6 bytes, found 5 — change `s:6` to `s:5`". `unserialize()` still performs the real
conversion, as required; the tokenizer only decides whether it is safe to call.

A custom-serialized (`C:`) body is itself a serialized payload, so the three validation stages run
over it again before the payload is accepted — in place, at its real offsets, so a diagnostic still
points at a byte of the payload the caller passed. The body's depth and element count are spent
from the same budget as the payload around it, which is what stops a limit being split across the
nesting. A body that does not tokenize as one complete value is refused: the package cannot vouch
for bytes it cannot read, and `unserialize()` would hand them straight to the class.

`isValid()` stops after `PayloadPolicy`. `toArray()` stops after `SafeUnserializer` — it returns
the PHP value as PHP built it, objects and all, so a caller that wants the real object graph gets
it. Normalization is on the JSON path only.

### Why normalize before encoding

`json_encode()` serializes an object's **public** properties and silently drops the rest. An
allow-listed `Money` with a private `$amount` therefore encodes as `{}` — the package's central
promise, "show me what is in this payload", answered with an empty object and no error. The
normalizer closes that gap.

**Rules**

1. An object becomes a `stdClass`, never an array. An object whose property names are all numeric
   would otherwise encode as a JSON *array*, losing the object/array distinction the payload drew.
2. Property names are demangled by `PropertyName`: `\0Class\0name` (private) and `\0*\0name`
   (protected) both become `name`. The NUL-delimited spelling is a PHP storage detail, not data the
   user wrote. A name still holding a NUL once demangled — `\0` alone, or `\0A\0b\0c` — is not one
   PHP can assign to a `stdClass`, and casting the array instead would let `json_encode()` drop it
   without a word, so the policy refuses such a payload before `unserialize()` runs. `PropertyName`
   owns that rule for both stages: the normalizer asks it to split, the policy asks it whether the
   split survives.
3. **Colliding names are qualified, never dropped.** A child class redeclaring a parent's private
   property yields `\0Parent\0x` *and* `\0Child\0x`. When two properties demangle to one name,
   every member of that collision is written as `Class::name` — `Parent::x` and `Child::x`, with no
   bare `x`. Picking a winner would reintroduce the silent loss this stage exists to prevent.
4. **Enums are passed through untouched.** `json_encode()` already renders a backed enum as its
   value; casting one would turn `"h"` into `{"name": "h", "value": "h"}`.
5. Arrays are walked recursively; scalars are returned as they are. Recursion terminates because a
   cycle can only be serialized as `r:` or `R:`, which `PayloadPolicy` rejects wherever it appears —
   including inside a `C:` body, whose contents PHP resolves references against just the same.
6. The class name is **not** added to the output. `O:8:"stdClass":0:{}` encodes as `{}`, and an
   injected `__class` key could collide with a real property.

## Security Model

1. **`allowed_classes` is never `true`.** It is `false` when no classes are allowed (the
   default), or the explicit `allowedClasses` list otherwise. There is no path that passes `true`.
   The list is passed in the spelling `ClassAllowList` normalizes it to, never the caller's own:
   PHP matches `allowed_classes` case-insensitively but does not strip a leading `\`, so passing
   `'\Money'` through verbatim would leave the package believing a class allowed that PHP does not,
   and the `__PHP_Incomplete_Class` that results is exactly what rule 2 promises cannot happen.
2. **Objects are rejected before `unserialize()` runs.** If the tokenizer sees an `O:` or `C:`
   token whose class is not in `allowedClasses`, `UnsafeSerializedDataException` is thrown naming
   the class and its offset. **Allowing a class is only honoured when PHP can actually restore
   that class**, which `ClassRestorability` decides before `unserialize()` runs:
   - a name PHP cannot load would come back as a `__PHP_Incomplete_Class` — not the class the
     caller vouched for — so the pseudo-property `__PHP_Incomplete_Class_Name` can never reach the
     output;
   - an abstract class, interface or trait, or an enum written as an object token, makes
     `unserialize()` raise a raw `Error`, which would escape the package's exception hierarchy.

   Both are refused with an `UnsafeSerializedDataException` naming the class and its offset. An
   enum reached through an `E:` token is the one restorable-but-not-instantiable case, and is
   checked with `enum_exists()` instead.
3. **No `__wakeup`/`__destruct` gadget can fire** for a class the caller did not name. Allowing a
   class is an explicit, per-call decision by the consuming application.
4. **Limits are enforced on the token stream**, before any memory is allocated for the
   unserialized value — inside a `C:` body as well as around it. `maxElements` is enforced by the
   tokenizer as it lexes, not after: a 16 MB payload of four-byte tokens is 4M `Token` objects, so
   counting them only once they all exist is an out-of-memory kill rather than a limit. The
   element budget is spent across nested bodies too, which is what bounds the total work a payload
   can ask for. Each limit is decided in exactly one place: element count where tokens are made,
   depth where structure is known, byte length before either.
5. **The payload is never `eval`'d, included, or written to disk.**

**Only a warning means `unserialize()` failed.** The error handler wrapping the call captures
`E_WARNING` and `E_USER_WARNING`; everything quieter is handed back to PHP. A serialized object
carrying a property its class no longer declares raises `E_DEPRECATED` ("Creation of dynamic
property") on PHP 8.2+, and reading that as a failure would reject the legacy blobs this package
exists to read — while `isValid()`, which stops before `unserialize()`, called the same payload
good.

`SECURITY.md` documents this model and a private disclosure address.

## Error Handling

All exceptions implement `Serialized\Exceptions\SerializedException` (an interface), so callers
can `catch (SerializedException $e)` for everything, or narrow to one case.

| Exception | Thrown when |
|---|---|
| `InvalidSerializedDataException` | Malformed payload: truncated token, length mismatch, unbalanced braces, wrong element count, trailing bytes; or `unserialize()` itself warning that it could not read the payload, whose message the diagnostic carries |
| `UnsafeSerializedDataException` | Object of a class not in `allowedClasses`; an allow-listed class PHP cannot load or cannot restore; reference token (`R:`/`r:`) |
| `UnrepresentableValueException` | Value valid in PHP but not in JSON: a property name that still holds a NUL once demangled, non-UTF-8 string, a float whose value is not finite — spelled `NAN`/`INF`/`-INF` or overflowing the double range like `1e999` — a non-backed enum case |
| `LimitExceededException` | `maxBytes`, `maxDepth`, or `maxElements` exceeded |
| `JsonEncodingException` | `json_encode` failed despite validation (should be unreachable; wraps `JsonException`) |

Every exception carries a `Diagnostic` value object — the `payload`, the byte `offset`, the
`reason` and the `fix` — rendered into the message by a shared `SnippetRenderer`, and readable
programmatically via `$e->diagnostic()` so unserialize.dev can highlight the exact byte.

`SerializedException::diagnostic()` returns `?Diagnostic`, because `JsonEncodingException` cannot
trace its failure to a byte. Every other exception narrows the return type to `Diagnostic` and
takes one in a private constructor, so catching a specific exception never needs a null check.

The rendered snippet is always pure ASCII — unprintable bytes become `\xNN` escapes, the
truncation marker is `...` — so a byte offset into the snippet is also its display column, in any
terminal and any encoding.

```
Serialized\Exceptions\InvalidSerializedDataException

  String length mismatch at offset 24.

  s:4:"name";s:6:"Chrome";
            ^ declared 6 bytes, found 5

  Fix: change s:6 to s:5, or restore the missing byte in the value.
```

```
Serialized\Exceptions\UnsafeSerializedDataException

  Payload contains an object of class "App\Models\User" at offset 42.
  Objects are rejected by default because unserializing them can invoke
  __wakeup() and __destruct() on attacker-controlled data.

  Fix: if you trust this payload, allow the class explicitly:
       Serialized::make()->allowClasses([App\Models\User::class])
```

`tryToJson()` catches `Throwable` and returns `null`. A payload that makes PHP raise something
outside the package hierarchy (an `Error` from memory exhaustion, an extension warning promoted to
an exception) must not escape a method whose whole contract is "never throws". `toJson()` remains
the throwing path for callers who want the diagnostic.

## Project Structure

```
src/
  Serialized.php                          Static entry point; delegates to SerializedConverter
  SerializedConverter.php                 Immutable fluent builder; orchestrates the pipeline
  Options.php                             readonly value object: limits, allowed classes, JSON flags
  PropertyName.php                        readonly value object: a property's storage key, split

  Tokenizer/
    Tokenizer.php                         string → Token[]
    Token.php                             readonly: TokenType, offset, length, raw, declaredLength
    TokenType.php                         enum: Null, Boolean, Integer, Float, String, Array,
                                          Close, Object, CustomObject, Reference, ValueReference, Enum

  Parser/
    Parser.php                            Token[] → ParsedPayload; structural rules
    ParsedPayload.php                     readonly: depth, elementCount, classNames, referenceOffset,
                                          propertyNames (only those holding a NUL byte)
    StructureFrame.php                    One open array or object while the parser walks

  Policy/
    PayloadPolicy.php                     Applies Options to ParsedPayload
    ClassAllowList.php                    Owns the allowed-class decision
    ClassRestorability.php                Owns whether PHP can rebuild a named class
    JsonRepresentability.php              Rejects values JSON cannot carry

  Conversion/
    SafeUnserializer.php                  Wraps unserialize() + error handling
    ValueNormalizer.php                   Objects → stdClass, property names demangled
    JsonEncoder.php                       Wraps json_encode() + flag handling

  Diagnostics/
    Diagnostic.php                        readonly: payload, offset, reason, fix
    SnippetRenderer.php                   Renders the caret snippet shown above
    DiagnosticMessage.php                 Assembles reason + snippet + fix into the message

  Exceptions/
    SerializedException.php               interface: diagnostic(): ?Diagnostic
    CarriesDiagnostic.php                 trait: private constructor + narrowed diagnostic()
    InvalidSerializedDataException.php
    UnsafeSerializedDataException.php
    UnrepresentableValueException.php
    LimitExceededException.php
    JsonEncodingException.php

tests/
  Unit/                                   One file per src class, mirroring the namespace
  Feature/                                End-to-end: payload in, JSON out
  Fixtures/                               Real-world payloads (WordPress meta, sessions, …)
  Pest.php

composer.json  phpunit.xml  phpstan.neon  pint.json
README.md  SECURITY.md  CHANGELOG.md  LICENSE.md  CLAUDE.md  SPEC.md
```

No `.github/` workflows for 1.0 — CI is deliberately deferred.

## Code Style

Pint with the `laravel` preset. PHP 8.4 idioms are the default, not the exception: `readonly`
classes, constructor property promotion, enums with behaviour, `match`, first-class callables,
named arguments at call sites with more than two arguments.

```php
<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

enum TokenType: string
{
    case Array_ = 'a';
    case Object_ = 'O';
    case String_ = 's';
    case Integer = 'i';
    case Float_ = 'd';
    case Boolean = 'b';
    case Null_ = 'N';
    case Reference = 'R';

    public static function fromPrefix(string $prefix, int $offset): self
    {
        return self::tryFrom($prefix) ?? throw new InvalidSerializedDataException(
            new Diagnostic(
                offset: $offset,
                reason: sprintf('Unknown type prefix "%s".', $prefix),
                fix: 'Expected one of: a, O, s, i, d, b, N, R.',
            ),
        );
    }

    public function isScalar(): bool
    {
        return match ($this) {
            self::String_, self::Integer, self::Float_, self::Boolean, self::Null_ => true,
            self::Array_, self::Object_, self::Reference => false,
        };
    }

    public function declaresLength(): bool
    {
        return $this === self::String_;
    }
}
```

**Rules**

- `declare(strict_types=1)` in every file.
- One class per file; a class that needs a section comment to be navigable is two classes.
- No god classes: `Tokenizer` lexes, `Parser` structures, `PayloadPolicy` decides,
  `SafeUnserializer` converts, `JsonEncoder` encodes. None of them knows the next stage exists.
- Descriptive names in full words. `$declaredByteLength`, not `$len`. `rejectDisallowedClasses()`,
  not `check()`.
- Every parameter and return type is declared, including `mixed` and `void`. No `@param` docblocks
  that only repeat the signature; docblocks carry array shapes (`@param list<class-string>`).
- `match` over `switch`; enums over class constants; `readonly` over getters-with-no-logic.
- No duplicated logic. The caret snippet is rendered in exactly one place (`SnippetRenderer`);
  the allowed-class decision lives in exactly one place (`ClassAllowList`).
- Exception messages are built by the exception's own named constructors
  (`InvalidSerializedDataException::lengthMismatch(...)`), never assembled at the throw site.
- Every method carries a docblock — one concise sentence on what it does, plus the *why* when the
  code does not make it obvious. Private methods and named constructors included.
- Comments are concise. No changelog comments — no "changed X", "was Y", "added in 1.2", no
  commented-out code, no dates or author names. Inline comments are for the non-obvious only.

## Testing Strategy

Pest 5. `tests/Unit` mirrors `src/` one file per class; `tests/Feature` covers the public API
end to end. `tests/Fixtures` holds real payloads (the Chrome example, WordPress `_wp_attachment_metadata`,
a Laravel session blob, a deeply nested array).

**Coverage: 100% line coverage of `src/`** (`composer test -- --coverage --min=100`, run locally).
The package is small, pure, and has no I/O — anything less means an untested branch in a security
boundary.

Every one of these must have a test before the corresponding code is considered done:

| Area | Cases |
|---|---|
| Happy path | The Chrome payload; nested arrays; every scalar type; empty array; empty string; integer and string keys |
| Malformed | Truncated payload; `s:6` for a 5-byte string; unbalanced `{`/`}`; wrong element count in an array or an object, each named with its own header spelling; trailing bytes after a complete value; unknown type prefix; empty input |
| Security | `O:` object rejected by default; `O:` object accepted when allow-listed; `C:` custom object; nested object inside an allowed array; `R:`/`r:` reference rejected; an allow-listed class that PHP cannot load, and one it cannot instantiate, both rejected; an allow-list entry spelled `\Class`, `CLASS` or `\CLASS` honoured, with no `__PHP_Incomplete_Class_Name` in the output |
| `C:` bodies | A body holding `R:`/`r:` rejected; a body naming a class not on the allow-list rejected; a body deeper than the remaining depth budget, and one larger than the remaining element budget, rejected; a non-UTF-8 string and a `NAN` inside a body rejected with a byte offset into the whole payload; a body that is not a complete serialized value rejected; a well-formed body still converts |
| Property names | `\0` alone, `\0ab` and `\0A\0b\0c` as property names rejected with the name's byte offset, rather than an `Error` out of `toJson()`; a real private and a real protected name still convert; a NUL inside an array key or a string value still converts |
| Normalization | Private and protected properties appear in the JSON; a parent/child private collision yields `Parent::x` and `Child::x`; an object with only numeric property names stays a JSON object; a backed enum stays its value; an object nested in an array is normalized too |
| Limits | Payload over `maxBytes`; nesting over `maxDepth`; element count over `maxElements`; each limit raised via the builder and then passing; a payload whose token count passes `maxElements` is refused while lexing, reporting the ceiling rather than a total it never counted |
| Impossible counts | An array or object header declaring more pairs than the bytes that remain could hold, at the saturating boundary (`2^62`) and far past it, rejected as malformed rather than overflowing |
| Unrepresentable | Non-UTF-8 byte in a string; `d:NAN;`; `d:INF;`; `d:-INF;`; `d:1e999;` and `d:-1e999;`, which overflow to infinity rather than spelling it; a non-backed enum case, rejected with a byte offset rather than reaching `JsonEncodingException` |
| Diagnostics | Offset is byte-accurate; caret sits under the offending byte; `fix` text is present and non-generic |
| API | `tryToJson` returns `null` instead of throwing; `isValid` never throws; `toArray` returns the PHP value; builder methods return new instances and leave the original unchanged |

Tests assert on the *diagnostic* (offset, reason, fix), not on the full message string, so
wording can change without breaking the suite.

## Boundaries

**Always**

- Run `composer check` before declaring work done; the `Stop` hook already gates on the suite.
- Pass `allowed_classes` explicitly on every `unserialize()` call.
- Validate before converting — never call `unserialize()` on unvalidated input.
- Add a test for every new branch; the coverage gate is not negotiable down.
- Update `SPEC.md` when a decision changes, before the code changes.

**Ask first**

- Adding any runtime dependency to `require`.
- Adding a public method or changing a public signature (it is a versioned API contract).
- Changing a default limit or the default JSON flags.
- Lowering the coverage threshold, or adding a CI pipeline.
- Tagging a release or publishing to Packagist.

**Never**

- Call `unserialize()` with `allowed_classes => true`, or omit the option.
- Suppress errors with `@`, or swallow a `Throwable` without rethrowing a typed exception.
- Weaken a test, mark it skipped, or lower a threshold to get to green.
- Commit `vendor/`, `.idea/`, credentials, or a `composer.lock` for a library.
- Add framework-specific code to `src/`.

## Success Criteria

1. `Serialized::toJson($chromePayload)` returns exactly the JSON in **Objective**, byte for byte.
2. `Serialized::toJson('a:10:{s:4:"name";s:6:"Chrom";}')` throws `InvalidSerializedDataException`
   whose diagnostic reports offset, "declared 6 bytes, found 5", and a fix naming `s:5`.
3. `Serialized::toJson('O:8:"stdClass":0:{}')` throws `UnsafeSerializedDataException`;
   `Serialized::make()->allowClasses([stdClass::class])->toJson(...)` returns `{}`.
4. A 100 MB payload and a 500-level-deep payload both throw `LimitExceededException` without
   exhausting memory.
5. `Serialized::make()->allowClasses([Money::class])->toJson(serialize(new Money(5, 'USD')))`
   returns `{"amount": 5, "cur": "USD"}` — private and protected properties included, not `{}`.
6. `composer check` is green: Pint clean, PHPStan level max with zero errors, full suite passing,
   and `composer coverage` reports 100% of `src/`.
7. `composer require roelmagdaleno/serialized` in a fresh Laravel 12 app works with no extra wiring.
8. `README.md` shows installation, the four verbs, the builder, and the exception hierarchy.

## Out of Scope for 1.0

- `JSON → serialized` (the reverse direction). Deferred to 1.1 or a companion package.
- Laravel service provider / facade. unserialize.dev binds the converter in its own
  `AppServiceProvider`. Revisit only if other developers ask.
- Coercion flags (`->withInvalidUtf8Substitute()`, `->withNonFiniteAsNull()`). 1.0 rejects
  unrepresentable values outright; coercion is additive and can ship in 1.1 without a breaking change.
- Streaming / chunked parsing for payloads larger than memory.
- Emitting class names alongside normalized objects (`->withClassNames()`, adding a `__class` key
  or an envelope). Additive, and it can ship in 1.1 without breaking the 1.0 output.

## Resolved Decisions

1. **`composer.json` `version` field** — removed. Packagist derives versions from git tags.
2. **`tryToJson` breadth** — catches `Throwable`, not just `SerializedException`.
3. **CI** — none for 1.0. `composer check` run locally is the gate.
4. **`composer.lock`** — untracked, as a library should be. `.gitignore` carries it.
5. **Normalized objects become `stdClass`, not arrays** — keeps the object/array distinction the
   payload drew, including for objects whose property names are all numeric.
6. **Colliding demangled property names are qualified as `Class::name`, never dropped** — silently
   preferring one of a parent/child private pair is the bug this stage exists to remove.
7. **Class names stay out of the output** — a `__class` key can collide with a real property, and
   `{}` for an empty object is the documented result.
8. **Non-backed enums are rejected, not coerced** — consistent with 1.0 rejecting every value JSON
   cannot carry. Rendering the case name is coercion, and belongs with the other 1.1 coercion flags.

## Open Questions

None open.
