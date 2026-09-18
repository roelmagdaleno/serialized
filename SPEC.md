# Spec: roelmagdaleno/serialized

> Status: **approved** (2026-09-18) · Phase 1 (Specify) of spec-driven development.
> Update this document before changing the code it describes.

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
composer test -- --coverage       # with coverage (requires Xdebug or PCOV)
composer pint                     # vendor/bin/pint        (fix formatting)
composer pint -- --test           # vendor/bin/pint --test (check only, used in CI)
composer stan                     # vendor/bin/phpstan analyse
composer check                    # pint --test && stan && test — the full gate
```

All four scripts are defined in `composer.json`. There is no CI pipeline for 1.0 — `composer check`
is run locally before any commit.

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
│                   │           unknown type letters, trailing bytes
└───────────────────┘
        │  Token[]
        ▼
┌───────────────────┐
│ Parser            │  Token[] → structural validation
│                   │  catches: unbalanced braces, wrong element counts,
│                   │           non-scalar array keys, depth, element count
└───────────────────┘
        │  ParsedPayload (depth, elementCount, classNames, flags)
        ▼
┌───────────────────┐
│ PayloadPolicy     │  applies Options against ParsedPayload
│                   │  rejects: disallowed classes, references (R:/r:),
│                   │           non-UTF-8 strings, NAN/INF floats, limits
└───────────────────┘
        │  (validated)
        ▼
┌───────────────────┐
│ SafeUnserializer  │  unserialize($payload, ['allowed_classes' => …])
│                   │  wraps warnings as exceptions; never returns false silently
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

`isValid()` stops after `PayloadPolicy`. `toArray()` stops after `SafeUnserializer`.

## Security Model

1. **`allowed_classes` is never `true`.** It is `false` when no classes are allowed (the
   default), or the explicit `allowedClasses` list otherwise. There is no path that passes `true`.
2. **Objects are rejected before `unserialize()` runs.** If the tokenizer sees an `O:` or `C:`
   token whose class is not in `allowedClasses`, `UnsafeSerializedDataException` is thrown naming
   the class and its offset. A `__PHP_Incomplete_Class` never reaches the caller.
3. **No `__wakeup`/`__destruct` gadget can fire** for a class the caller did not name. Allowing a
   class is an explicit, per-call decision by the consuming application.
4. **Limits are enforced on the token stream**, before any memory is allocated for the
   unserialized value.
5. **The payload is never `eval`'d, included, or written to disk.**

`SECURITY.md` documents this model and a private disclosure address.

## Error Handling

All exceptions implement `Serialized\Exceptions\SerializedException` (an interface), so callers
can `catch (SerializedException $e)` for everything, or narrow to one case.

| Exception | Thrown when |
|---|---|
| `InvalidSerializedDataException` | Malformed payload: truncated token, length mismatch, unbalanced braces, wrong element count, trailing bytes |
| `UnsafeSerializedDataException` | Object of a class not in `allowedClasses`; reference token (`R:`/`r:`) |
| `UnrepresentableValueException` | Value valid in PHP but not in JSON: non-UTF-8 string, `NAN`, `INF`, `-INF` |
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

  Tokenizer/
    Tokenizer.php                         string → Token[]
    Token.php                             readonly: TokenType, offset, length, raw, declaredLength
    TokenType.php                         enum: Array_, Object_, CustomObject, String_, Integer,
                                          Float_, Boolean, Null_, Reference, ObjectReference, Close

  Parser/
    Parser.php                            Token[] → ParsedPayload; structural rules
    ParsedPayload.php                     readonly: depth, elementCount, classNames, hasReferences

  Policy/
    PayloadPolicy.php                     Applies Options to ParsedPayload
    ClassAllowList.php                    Owns the allowed-class decision

  Conversion/
    SafeUnserializer.php                  Wraps unserialize() + error handling
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
| Malformed | Truncated payload; `s:6` for a 5-byte string; unbalanced `{`/`}`; wrong array element count; trailing bytes after a complete value; unknown type prefix; empty input |
| Security | `O:` object rejected by default; `O:` object accepted when allow-listed; `C:` custom object; nested object inside an allowed array; `R:`/`r:` reference rejected |
| Limits | Payload over `maxBytes`; nesting over `maxDepth`; element count over `maxElements`; each limit raised via the builder and then passing |
| Unrepresentable | Non-UTF-8 byte in a string; `d:NAN;`; `d:INF;`; `d:-INF;` |
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
5. `composer check` is green: Pint clean, PHPStan level max with zero errors, full suite passing,
   and `composer test -- --coverage --min=100` reports 100% of `src/`.
6. `composer require roelmagdaleno/serialized` in a fresh Laravel 12 app works with no extra wiring.
7. `README.md` shows installation, the four verbs, the builder, and the exception hierarchy.

## Out of Scope for 1.0

- `JSON → serialized` (the reverse direction). Deferred to 1.1 or a companion package.
- Laravel service provider / facade. unserialize.dev binds the converter in its own
  `AppServiceProvider`. Revisit only if other developers ask.
- Coercion flags (`->withInvalidUtf8Substitute()`, `->withNonFiniteAsNull()`). 1.0 rejects
  unrepresentable values outright; coercion is additive and can ship in 1.1 without a breaking change.
- Streaming / chunked parsing for payloads larger than memory.

## Resolved Decisions

1. **`composer.json` `version` field** — removed. Packagist derives versions from git tags.
2. **`tryToJson` breadth** — catches `Throwable`, not just `SerializedException`.
3. **CI** — none for 1.0. `composer check` run locally is the gate.

## Open Questions

1. **`composer.lock` is tracked in git.** For a library it pins nothing for consumers and only
   causes merge noise. Untrack it (`git rm --cached composer.lock` + `.gitignore`)?
