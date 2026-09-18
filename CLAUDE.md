# CLAUDE.md

`roelmagdaleno/serialized` — a PHP 8.4 package that converts PHP serialized data into
pretty-printed JSON, safely. Published on Packagist, consumed by https://unserialize.dev
and other PHP developers.

**`SPEC.md` is the source of truth** for the architecture, the pipeline, the public API, the
security model, and the success criteria. Read it before changing anything in `src/`. If a
decision changes, update `SPEC.md` first, then the code.

## Commands

```bash
composer test          # vendor/bin/pest
composer pint          # vendor/bin/pint        (fix)
composer pint -- --test
composer stan          # vendor/bin/phpstan analyse
composer check         # pint --test && stan && test — run this before you finish
```

A `PostToolUse` hook runs Pint and PHPStan on every PHP file you edit, and a `Stop` hook runs
the suite. Do not work around either one.

## Code conventions

- **PHP 8.4, modern idioms by default.** `readonly` classes, constructor property promotion,
  enums with behaviour, `match` (never `switch`), first-class callables, named arguments when a
  call takes more than two.
- `declare(strict_types=1);` in every file. Every parameter and return type declared.
- **No god classes.** One class, one job. `Tokenizer` lexes; `Parser` structures;
  `PayloadPolicy` decides; `SafeUnserializer` converts; `JsonEncoder` encodes. A stage never
  knows what comes after it. If a class needs section comments to navigate, it is two classes.
- **Don't repeat yourself.** Each decision lives in exactly one place — the caret snippet is
  rendered only by `SnippetRenderer`, the allowed-class rule only by `ClassAllowList`. Before
  writing a helper, look for the one that already exists.
- **Descriptive names, full words.** `$declaredByteLength`, not `$len`.
  `rejectDisallowedClasses()`, not `check()`. A name that needs a comment is the wrong name.
- **Readable over clever.** Guard clauses over nesting; early returns; small methods.
- **Comments are concise and explain *why*, never *what*.** One line where one line does. No
  changelog comments — no "changed X", "was Y", "added in 1.2", no commented-out old code, no
  dates or author names. Git history records what changed; the comment records why the code is
  the way it is. If nothing non-obvious needs saying, write no comment.
- Exception messages are built by named constructors on the exception
  (`InvalidSerializedDataException::lengthMismatch(...)`), never assembled at the throw site.
- Docblocks only for what the signature cannot say (array shapes, `list<class-string>`).

## Security rules (non-negotiable)

- `unserialize()` is called with an explicit `allowed_classes` — `false` by default, or the
  caller's allow-list. **Never `true`, never omitted.**
- Nothing reaches `unserialize()` that the tokenizer and policy have not already validated.
- Objects, references, and payloads over the configured limits are rejected *before* any memory
  is allocated for the value.
- No `@` suppression. No swallowed `Throwable` — wrap it in a typed package exception.

## Testing

Pest 5. `tests/Unit` mirrors `src/` one file per class; `tests/Feature` covers the public API
end to end; `tests/Fixtures` holds real payloads. **100% line coverage of `src/`** — the package
is small and pure, so a missing branch means an untested security boundary. Check it locally with
`composer test -- --coverage --min=100`.

Assert on the exception's `Diagnostic` (offset, reason, fix), not on the full message string.

Every behaviour change is test-first: red, green, refactor. Never weaken, skip, or delete a test
to reach green — fix the code or raise it with me.

## Boundaries

**Ask first:** adding a runtime dependency; adding or changing a public method signature;
changing a default limit or the default JSON flags; lowering the coverage threshold;
adding CI; tagging a release or publishing to Packagist.

**Never:** framework-specific code in `src/` (the core stays free of Laravel); committing
`vendor/`, `.idea/`, or a `composer.lock` for a library.
