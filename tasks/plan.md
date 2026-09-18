# Implementation Plan: roelmagdaleno/serialized

> Phase 2 (Plan) of spec-driven development · **complete** — all 14 tasks done, all checkpoints met
> Source of truth: `SPEC.md`. Tasks: `tasks/todo.md`.

## Overview

Build the five-stage pipeline described in `SPEC.md` (`Tokenizer → Parser → PayloadPolicy →
SafeUnserializer → JsonEncoder`) behind the `Serialized` / `SerializedConverter` public API.

The build is sliced **vertically**: the first slice drives a single scalar payload through every
stage of the pipeline end to end. Every slice after that widens what the pipeline accepts —
arrays, then objects, then limits, then unrepresentable values — rather than adding another
horizontal layer. The architecture is therefore proven by a passing end-to-end test at Task 5,
not at the end of the build.

## Architecture Decisions

Recorded in `SPEC.md`; repeated here only where they shape the build order.

- **Diagnostics come first.** Every stage throws exceptions carrying a `Diagnostic`, so the
  diagnostics and exception layer is the one true foundation and is built before any stage.
- **The walking skeleton is a scalar.** `Serialized::toJson('i:42;')` exercises all five stages
  with the least possible code. If the stage boundaries are wrong, we find out in Task 5 rather
  than in Task 12.
- **`Options` grows with the slices.** Task 4 introduces it with JSON flags only; `allowClasses`
  lands in Slice 3 and the three limits in Slice 4, each alongside the code that reads them.
  No option is added before something enforces it.
- **The tokenizer slices strings by declared byte length, never by scanning for the closing
  quote.** `s:5:"a";b";` is a legal serialized string containing `a";b`. Scanning is the single
  most common way a hand-written serialized parser gets this wrong, and it is a correctness *and*
  security bug. This is Task 2's central test case.
- **The tokenizer is a gatekeeper, not a value producer.** It decides whether `unserialize()` may
  be called; `unserialize()` still produces the value. We never reimplement PHP's deserialization.

## Dependency Graph

```
Diagnostic + SnippetRenderer + Exceptions          (T1)
        │
        ├──────────────┬────────────────┐
        ▼              ▼                ▼
    Tokenizer      SafeUnserializer  JsonEncoder    (T2, T4)
    (scalars)
        │              └────────┬───────┘
        ▼                       │
     Parser + ParsedPayload     │                   (T3)
        │                       │
        └───────────┬───────────┘
                    ▼
        Options + SerializedConverter + Serialized  (T5)  ◀── end-to-end, scalars
                    │
        ┌───────────┼────────────┬─────────────┐
        ▼           ▼            ▼             ▼
    Arrays      Objects      Limits      Unrepresentable
    (T6, T7)    (T8, T9)     (T10)       (T11)
        │           │            │             │
        └───────────┴─────┬──────┴─────────────┘
                          ▼
            JSON flag options (T12) → coverage sweep (T13) → docs (T14)
```

Bottom-up order, except that T2/T3 and T4 are independent of each other (see Parallelization).

## Task List

### Phase 1: Foundation and walking skeleton
- [x] T1: Diagnostics and exception hierarchy
- [x] T2: Tokenizer — scalar tokens
- [x] T3: Parser and ParsedPayload — scalar root
- [x] T4: SafeUnserializer, JsonEncoder and Options
- [x] T5: SerializedConverter, Serialized and the four verbs

**Checkpoint A** — the pipeline is real

### Phase 2: Arrays (the Objective criterion)
- [x] T6: Tokenizer — array and close tokens
- [x] T7: Parser — array structure, depth and element counting

**Checkpoint B** — the Chrome payload converts byte for byte

### Phase 3: Security
- [x] T8: Tokenizer and Parser — object, custom-object and reference tokens
- [x] T9: ClassAllowList, PayloadPolicy and `allowClasses()`

**Checkpoint C** — no object or reference reaches `unserialize()` unapproved

### Phase 4: Hardening
- [x] T10: Limits — maxBytes, maxDepth, maxElements
- [x] T11: Unrepresentable values — non-UTF-8, NAN, INF, -INF

**Checkpoint D** — every rejection path in the spec is covered

### Phase 5: Surface and release readiness
- [x] T12: JSON output options — `pretty()`, `compact()`, `withJsonFlags()`
- [x] T13: Coverage sweep to 100% of `src/`
- [x] T14: README, SECURITY.md, LICENSE.md, CHANGELOG.md

**Checkpoint E** — all seven Success Criteria in `SPEC.md` met

Full task bodies — acceptance criteria, verification, files, scope — are in `tasks/todo.md`.

## Verification Checkpoints

Every checkpoint requires `composer check` green (Pint clean, PHPStan level max, suite passing)
plus the specific condition below. Each is a review gate: stop and report, do not roll on.

| Checkpoint | After | Condition |
|---|---|---|
| **A** | T5 | `Serialized::toJson('i:42;') === '42'`, and `'s:6:"Chrome";'` → `'"Chrome"'`. All five stages execute. `tryToJson`, `toArray`, `isValid` work for scalars. |
| **B** | T7 | `Serialized::toJson($chromePayload)` equals the JSON in `SPEC.md` byte for byte (Success Criterion 1). Truncated, unbalanced and miscounted arrays throw with a byte-accurate offset (Criterion 2). |
| **C** | T9 | `'O:8:"stdClass":0:{}'` throws `UnsafeSerializedDataException`; the same payload with `allowClasses([stdClass::class])` returns `{}` (Criterion 3). No `__PHP_Incomplete_Class` can be produced by any input. |
| **D** | T11 | A 100 MB payload and a 500-deep payload both throw `LimitExceededException` without exhausting memory (Criterion 4). Non-UTF-8, NAN, INF and -INF each throw `UnrepresentableValueException`. |
| **E** | T14 | All seven Success Criteria. `composer check` green and coverage at 100% of `src/`. |

## Parallelization

The build is mostly a dependency chain, but three groups are genuinely independent:

- **Safe to parallelize:** T4 (`SafeUnserializer` + `JsonEncoder` + `Options`) against T2/T3
  (`Tokenizer` + `Parser`). They share only T1 and never call each other. T14's documentation can
  be drafted any time after Checkpoint C.
- **Must be sequential:** T2 → T3 → T5, and T6 → T7. The parser consumes the tokenizer's output;
  the converter needs every stage to exist.
- **Needs coordination:** T8 and T9 share the object-token contract. T8 defines what the tokenizer
  records about a class (name, offset); T9 consumes it. Fix that contract in T8 before starting T9.

Given the size of the package, sequential execution is the recommendation. The parallel paths save
little and cost a merge.

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| **Tokenizer disagrees with PHP's own parser.** A payload our tokenizer accepts but `unserialize()` rejects (or worse, the reverse) breaks the safety guarantee. | **High** | Every rejection test asserts *both* that we throw and that `unserialize()` also fails on that payload. Every acceptance test round-trips through real `serialize()` output. A differential test feeds generated payloads to both. |
| **String length is bytes, not characters,** and the value may contain `"`, `;`, `}` or NUL. Scanning for the delimiter instead of slicing by length is the classic parser bug. | **High** | T2 acceptance requires `s:5:"a";b";` and a multibyte string to tokenize correctly. `mb_*` functions are banned inside the tokenizer — `strlen`/`substr` only. |
| **`unserialize()` signals failure with `E_WARNING`,** which is easy to miss and can leak to the caller's error handler. | **Medium** | `SafeUnserializer` wraps the call in `set_error_handler`, converts to a typed exception, and always restores the previous handler in a `finally`. `phpunit.xml` has `failOnWarning="true"` so a leak fails the suite. |
| ~~No coverage driver installed~~ **Resolved:** PCOV cannot be built (Herd ships no `phpize`), so `composer coverage` loads Herd's bundled Xdebug on demand. | — | `composer coverage` verified working and gating at 100%. |
| **Depth must be counted on the token stream, not by recursion,** or the guard itself blows the stack on the payload it exists to reject. | **Medium** | `Parser` uses an explicit stack, never recursion. T10 tests a 500-deep payload and asserts it throws rather than fatals. |
| **`allowClasses()` is a foot-gun** — allowing a class permits its `__wakeup`/`__destruct` on untrusted data. | **Medium** | README documents it as a trust decision with a worked gadget example. The exception message names the specific class rather than suggesting a blanket opt-out. |
| **Scope creep into a full deserializer.** Once a tokenizer exists it is tempting to have it produce values and drop `unserialize()`. | **Low** | The spec requires `unserialize()`. `ParsedPayload` deliberately carries only metadata (depth, counts, class names) — never values. |

## Open Questions

None outstanding. Coverage, `laravel/pao` and `composer.lock` were all resolved before T1.
