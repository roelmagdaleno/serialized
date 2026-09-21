# CLAUDE.md

`roelmagdaleno/serialized` — a PHP 8.4 package that converts PHP serialized data into
pretty-printed JSON, safely.

## Where things are written down

Read the relevant one before you work; do not restate it here.

| Doc | Holds |
|---|---|
| [`docs/architecture.md`](docs/architecture.md) | The pipeline, the stage boundaries, where each guarantee is enforced, the project layout. **Read before changing anything in `src/`.** |
| [`docs/adr/`](docs/adr/README.md) | Why a decision was made, and what was rejected. Immutable. |
| [`docs/testing.md`](docs/testing.md) | Test layout, the coverage gate, the cases that must stay covered. |
| [`README.md`](README.md) | The public API: four verbs, the builder, every `DiagnosticCode` and its `context`. |
| [`SECURITY.md`](SECURITY.md) | The safety promise made to callers. |
| [`CHANGELOG.md`](CHANGELOG.md) | What changed and why it mattered. |

When a decision changes, update the doc that owns it **before** the code, and add an ADR if
something was rejected along the way.

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
- **Every method gets a docblock**, including private ones and named constructors. One concise
  sentence saying what it does, then a second only where the *why* is not obvious from the code.
- **Comments are concise.** One line where one line does. No changelog comments — no "changed X",
  "was Y", "added in 1.2", no commented-out old code, no dates or author names. Git history
  records what changed; the comment records what the code does and why it is the way it is.
- Inline comments inside a method body are for the non-obvious only — a security consequence, a
  format quirk, a deliberate deviation. Never narrate the next line.
- Exception messages are built by named constructors on the exception
  (`InvalidSerializedDataException::lengthMismatch(...)`), never assembled at the throw site.
- Docblocks also carry what the signature cannot say (array shapes, `list<class-string>`).

## Security rules (non-negotiable)

- `unserialize()` is called with an explicit `allowed_classes` — `false` by default, or the
  caller's allow-list. **Never `true`, never omitted.**
- Nothing reaches `unserialize()` that the tokenizer and policy have not already validated.
- Objects, references, and payloads over the configured limits are rejected *before* any memory
  is allocated for the value.
- No `@` suppression. No swallowed `Throwable` — wrap it in a typed package exception.

## Testing

Test-first for every behaviour change: red, green, refactor. 100% line coverage of `src/`, never
lowered. Assert on the `Diagnostic` (code, offset, context), not on the message string. Never
weaken, skip or delete a test to reach green — fix the code or raise it with me.
Full strategy and the required cases: [`docs/testing.md`](docs/testing.md).

## Boundaries

**Ask first:** adding a runtime dependency; adding or changing a public method signature;
changing a default limit or the default JSON flags; lowering the coverage threshold;
adding CI; tagging a release or publishing to Packagist.

**Never:** framework-specific code in `src/` (the core stays free of Laravel); committing
`vendor/`, `.idea/`, or a `composer.lock` for a library.
