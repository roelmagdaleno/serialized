# Tasks: roelmagdaleno/serialized

> Plan: `tasks/plan.md` · Spec: `SPEC.md`
> Every task is test-first (red → green → refactor) and must leave `composer check` green.

---

## Phase 1: Foundation and walking skeleton

## Task 1 — ✅ done: Diagnostics and exception hierarchy

**Description:** Build the layer every other stage throws through: the `Diagnostic` value object
(offset, reason, fix, fragment), the `SnippetRenderer` that draws the caret snippet, and the five
exceptions behind the `SerializedException` interface. No exception message is assembled at a
throw site — each exception gets named constructors instead.

**Acceptance criteria:**
- [x] `Diagnostic` is `readonly` and exposes `offset`, `reason`, `fix`, `fragment`.
- [x] `SnippetRenderer` places the caret under the exact byte for an offset at the start, middle
      and end of a payload, and truncates long payloads around the offset with an ellipsis.
- [x] All five exceptions implement `SerializedException`, extend an appropriate SPL exception,
      and expose `diagnostic(): Diagnostic`.
- [x] Rendering lives only in `SnippetRenderer`; no exception builds its own snippet.

**Verification:**
- [x] `composer test -- tests/Unit/Diagnostics tests/Unit/Exceptions`
- [x] `composer stan`
- [x] Manual: `echo (string) InvalidSerializedDataException::lengthMismatch(...)` matches the
      formatting in the **Error Handling** section of `SPEC.md`.

**Dependencies:** None

**Files likely touched:**
- `src/Diagnostics/Diagnostic.php`, `src/Diagnostics/SnippetRenderer.php`
- `src/Exceptions/*.php` (6 files: interface + 5 exceptions)
- `tests/Unit/Diagnostics/SnippetRendererTest.php`, `tests/Unit/Exceptions/ExceptionsTest.php`

**Estimated scope:** Medium (the exception files are near-identical shells; the renderer is the
only real logic)

---

## Task 2 — ✅ done: Tokenizer — scalar tokens

**Description:** `Tokenizer::tokenize(string $payload): array` handling `i`, `d`, `b`, `N` and `s`
at the root, plus `TokenType` and the `readonly Token`. Strings are sliced by their declared byte
length — never by scanning for the closing quote.

**Acceptance criteria:**
- [x] Each scalar type produces one `Token` with a byte-accurate offset and the raw fragment.
- [x] `s:5:"a";b";` tokenizes as one string token whose value is `a";b` — the delimiter-scanning
      bug is covered by a test.
- [x] Malformed input throws `InvalidSerializedDataException` with the offset, the reason, and a
      fix: truncated token, length mismatch, unknown type prefix, missing terminator, empty input,
      trailing bytes after a complete value.
- [x] No `mb_*` call anywhere in the tokenizer; `strlen`/`substr` only.

**Verification:**
- [x] `composer test -- tests/Unit/Tokenizer`
- [x] Every rejection test also asserts real `unserialize()` fails on the same payload.
- [x] `composer stan`

**Dependencies:** T1

**Files likely touched:**
- `src/Tokenizer/Tokenizer.php`, `src/Tokenizer/Token.php`, `src/Tokenizer/TokenType.php`
- `tests/Unit/Tokenizer/TokenizerTest.php`

**Estimated scope:** Medium

---

## Task 3 — ✅ done: Parser and ParsedPayload — scalar root

**Description:** `Parser::parse(array $tokens): ParsedPayload` validating that the token stream
forms exactly one complete value. Scalars only for now; the structural rules arrive in T7. Depth
and element count are tracked with an explicit stack, never recursion.

**Acceptance criteria:**
- [x] A single scalar token stream yields `ParsedPayload` with `depth = 1`, `elementCount = 1`,
      no class names, no references.
- [x] Zero tokens, or more than one root value, throws `InvalidSerializedDataException` with an
      offset.
- [x] `ParsedPayload` is `readonly` and carries metadata only — no unserialized values.

**Verification:**
- [x] `composer test -- tests/Unit/Parser`
- [x] `composer stan`

**Dependencies:** T2

**Files likely touched:**
- `src/Parser/Parser.php`, `src/Parser/ParsedPayload.php`
- `tests/Unit/Parser/ParserTest.php`

**Estimated scope:** Small

---

## Task 4 — ✅ done: SafeUnserializer, JsonEncoder and Options

**Description:** The two conversion wrappers plus the `Options` value object, introduced here with
JSON flags only. `SafeUnserializer` calls `unserialize()` with an explicit `allowed_classes` and
converts its `E_WARNING` into a typed exception. `JsonEncoder` wraps `json_encode` with the
default flags.

**Acceptance criteria:**
- [x] `SafeUnserializer` always passes `allowed_classes`; a test asserts `true` is never passed.
- [x] Its `E_WARNING` is captured, converted to `InvalidSerializedDataException` carrying the
      offset PHP reports, and the previous error handler is restored even when it throws.
- [x] `JsonEncoder` defaults to `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
      JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR` and wraps `JsonException` as
      `JsonEncodingException`.
- [x] `Options` is `readonly` with the spec's defaults.

**Verification:**
- [x] `composer test -- tests/Unit/Conversion`
- [x] Manual: a test that triggers the warning path asserts no PHP warning escapes
      (`failOnWarning` in `phpunit.xml` enforces this).
- [x] `composer stan`

**Dependencies:** T1 (independent of T2/T3 — parallelizable)

**Files likely touched:**
- `src/Conversion/SafeUnserializer.php`, `src/Conversion/JsonEncoder.php`, `src/Options.php`
- `tests/Unit/Conversion/SafeUnserializerTest.php`, `tests/Unit/Conversion/JsonEncoderTest.php`

**Estimated scope:** Medium

---

## Task 5 — ✅ done: SerializedConverter, Serialized and the four verbs

**Description:** Wire the stages together and expose the public API. `SerializedConverter` is
immutable and orchestrates the pipeline; `Serialized` is a thin static delegator. This closes the
walking skeleton: a scalar payload goes in, JSON comes out.

**Acceptance criteria:**
- [x] `toJson`, `tryToJson`, `toArray` and `isValid` exist on both classes and agree.
- [x] `tryToJson` catches `Throwable` and returns `null`; `isValid` never throws.
- [x] `isValid` stops after validation and `toArray` stops after unserializing — neither encodes
      JSON.
- [x] A `with*` method returns a new instance and leaves the original unchanged.

**Verification:**
- [x] `composer test -- tests/Feature`
- [x] Manual: `Serialized::toJson('i:42;') === '42'` and `Serialized::toJson('s:6:"Chrome";') === '"Chrome"'`
- [x] `composer check`

**Dependencies:** T3, T4

**Files likely touched:**
- `src/SerializedConverter.php`, `src/Serialized.php`
- `tests/Feature/ScalarConversionTest.php`, `tests/Unit/SerializedConverterTest.php`

**Estimated scope:** Medium

---

### Checkpoint A: the pipeline is real
- [x] `composer check` green
- [x] All five stages execute for a scalar payload
- [x] The four verbs behave per spec
- [x] **Review with human before proceeding**

---

## Phase 2: Arrays

## Task 6 — ✅ done: Tokenizer — array and close tokens

**Description:** Extend the tokenizer with `a:N:{` and the `}` close token, producing a flat token
stream for arbitrarily nested arrays. Still no structural validation — that is T7's job.

**Acceptance criteria:**
- [x] `a:2:{i:0;s:1:"a";i:1;s:1:"b";}` produces the expected token sequence with correct offsets.
- [x] Nested arrays tokenize; the declared element count is recorded on the array token.
- [x] A malformed array header (`a:x:{`, missing `{`, missing `:`) throws with an offset and fix.
- [x] No change to scalar tokenizing — T2's tests still pass untouched.

**Verification:**
- [x] `composer test -- tests/Unit/Tokenizer`
- [x] `composer stan`

**Dependencies:** T5

**Files likely touched:**
- `src/Tokenizer/Tokenizer.php`, `src/Tokenizer/TokenType.php`
- `tests/Unit/Tokenizer/TokenizerTest.php`

**Estimated scope:** Small

---

## Task 7 — ✅ done: Parser — array structure, depth and element counting

**Description:** The structural rules: braces balance, each array holds exactly its declared number
of key/value pairs, keys are only integers or strings. Depth and total element count are
accumulated here for T10 to police.

**Acceptance criteria:**
- [x] The Chrome payload parses, reporting `depth = 2` and the correct element count.
- [x] Each of these throws with a byte-accurate offset and a concrete fix: unbalanced `{`/`}`,
      declared count higher than actual, declared count lower than actual, a non-scalar array key,
      trailing bytes after the closing brace.
- [x] Depth is computed with an explicit stack; a 10,000-deep payload does not exhaust the stack.

**Verification:**
- [x] `composer test -- tests/Unit/Parser tests/Feature`
- [x] Manual: `Serialized::toJson($chromePayload)` matches `SPEC.md` byte for byte.
- [x] `composer check`

**Dependencies:** T6

**Files likely touched:**
- `src/Parser/Parser.php`, `src/Parser/ParsedPayload.php`
- `tests/Unit/Parser/ParserTest.php`, `tests/Feature/ArrayConversionTest.php`
- `tests/Fixtures/chrome.txt`

**Estimated scope:** Medium

---

### Checkpoint B: the Objective criterion
- [x] `composer check` green
- [x] Success Criterion 1: the Chrome payload converts byte for byte
- [x] Success Criterion 2: a length mismatch reports offset, reason and fix
- [x] **Review with human before proceeding**

---

## Phase 3: Security

## Task 8 — ✅ done: Tokenizer and Parser — object, custom-object and reference tokens

**Description:** Recognise `O:len:"Class":N:{`, `C:len:"Class":len:{...}` and the `R:`/`r:`
reference tokens. Record every class name with its offset on `ParsedPayload`, and flag whether the
payload contains references. Recognition only — the accept/reject decision is T9's.

**Acceptance criteria:**
- [x] `O:`, `C:`, `R:` and `r:` tokenize with byte-accurate offsets.
- [x] `ParsedPayload::classNames` lists every class name and offset, including classes nested
      inside arrays and inside other objects.
- [x] `ParsedPayload::hasReferences` is true when any `R:`/`r:` token is present.
- [x] A malformed object header throws with an offset and fix.

**Verification:**
- [x] `composer test -- tests/Unit/Tokenizer tests/Unit/Parser`
- [x] `composer stan`

**Dependencies:** T7

**Files likely touched:**
- `src/Tokenizer/Tokenizer.php`, `src/Tokenizer/TokenType.php`, `src/Parser/Parser.php`,
  `src/Parser/ParsedPayload.php`
- `tests/Unit/Tokenizer/TokenizerTest.php`, `tests/Unit/Parser/ParserTest.php`

**Estimated scope:** Medium

---

## Task 9 — ✅ done: ClassAllowList, PayloadPolicy and `allowClasses()`

**Description:** The security gate. `PayloadPolicy` applies `Options` to a `ParsedPayload` and
rejects disallowed classes and references before `unserialize()` is ever called. `ClassAllowList`
owns the allow/deny decision and is the only place that decision is made.

**Acceptance criteria:**
- [x] `'O:8:"stdClass":0:{}'` throws `UnsafeSerializedDataException` naming the class and offset;
      with `allowClasses([stdClass::class])` it returns `{}`.
- [x] A disallowed class nested inside an allowed object, or inside an array, is still rejected.
- [x] `R:`/`r:` references throw `UnsafeSerializedDataException`.
- [x] `SafeUnserializer` receives `false` or the explicit list — a test asserts no code path
      produces `__PHP_Incomplete_Class`.

**Verification:**
- [x] `composer test -- tests/Unit/Policy tests/Feature`
- [x] Manual: a gadget-shaped payload (class with `__wakeup`) is rejected by default.
- [x] `composer check`

**Dependencies:** T8

**Files likely touched:**
- `src/Policy/PayloadPolicy.php`, `src/Policy/ClassAllowList.php`, `src/Options.php`,
  `src/SerializedConverter.php`
- `tests/Unit/Policy/PayloadPolicyTest.php`, `tests/Feature/SecurityTest.php`

**Estimated scope:** Medium

---

### Checkpoint C: nothing unapproved reaches unserialize()
- [x] `composer check` green
- [x] Success Criterion 3 met
- [x] No input can produce a `__PHP_Incomplete_Class`
- [x] **Review with human before proceeding**

---

## Phase 4: Hardening

## Task 10 — ✅ done: Limits — maxBytes, maxDepth, maxElements

**Description:** Enforce the three limits and add their builder methods. `maxBytes` is checked
before tokenizing; depth and element count are checked on the token stream, before any value is
allocated.

**Acceptance criteria:**
- [x] Each limit throws `LimitExceededException` naming the limit, the configured value and the
      actual value.
- [x] `withMaxBytes`, `withMaxDepth`, `withMaxElements` each return a new instance; raising a
      limit makes a previously rejected payload pass.
- [x] A 100 MB payload is rejected without being tokenized, and a 500-deep payload is rejected
      without exhausting memory or the stack.

**Verification:**
- [x] `composer test -- tests/Feature/LimitsTest.php`
- [x] Manual: run the 100 MB case with `memory_limit=128M` and confirm it throws rather than fatals.
- [x] `composer check`

**Dependencies:** T9

**Files likely touched:**
- `src/Policy/PayloadPolicy.php`, `src/Options.php`, `src/SerializedConverter.php`
- `tests/Feature/LimitsTest.php`

**Estimated scope:** Small

---

## Task 11 — ✅ done: Unrepresentable values — non-UTF-8, NAN, INF, -INF

**Description:** Reject values that are valid PHP but cannot be expressed in JSON, detected on the
token stream so the exception can carry the offset.

**Acceptance criteria:**
- [x] A string containing a non-UTF-8 byte throws `UnrepresentableValueException` with the offset
      and a fix suggesting base64.
- [x] `d:NAN;`, `d:INF;` and `d:-INF;` each throw with the offset and a fix.
- [x] Valid multibyte UTF-8 (emoji, accents, CJK) still converts and is not escaped
      (`JSON_UNESCAPED_UNICODE`).
- [x] `json_encode` is never reached with a value it would reject — `JsonEncodingException` stays
      unreachable in practice.

**Verification:**
- [x] `composer test -- tests/Feature/UnrepresentableValuesTest.php`
- [x] `composer check`

**Dependencies:** T10

**Files likely touched:**
- `src/Policy/PayloadPolicy.php`, `src/Tokenizer/Tokenizer.php`
- `tests/Feature/UnrepresentableValuesTest.php`

**Estimated scope:** Small

---

### Checkpoint D: every rejection path covered
- [x] `composer check` green
- [x] Success Criterion 4 met
- [x] Every row of the spec's Testing Strategy table has a passing test
- [x] **Review with human before proceeding**

---

## Phase 5: Surface and release readiness

## Task 12 — ✅ done: JSON output options

**Description:** `pretty()`, `compact()` and `withJsonFlags()` on the builder, so consumers can
choose their output shape. Additive on top of the defaults.

**Acceptance criteria:**
- [x] `compact()` drops `JSON_PRETTY_PRINT`; `pretty()` restores it; the default stays pretty.
- [x] `withJsonFlags()` adds to the defaults rather than replacing them, and cannot remove
      `JSON_THROW_ON_ERROR`.
- [x] Each returns a new instance.

**Verification:**
- [x] `composer test -- tests/Unit/OptionsTest.php`
- [x] `composer check`

**Dependencies:** T11

**Files likely touched:**
- `src/Options.php`, `src/SerializedConverter.php`
- `tests/Unit/OptionsTest.php`

**Estimated scope:** Small

---

## Task 13 — ✅ done: Coverage sweep to 100% of `src/`

**Description:** Close every uncovered line and add the real-world fixtures. No production code
changes beyond deleting genuinely unreachable branches.

**Acceptance criteria:**
- [x] `composer test -- --coverage --min=100` passes over `src/`.
- [x] Fixtures exist for WordPress `_wp_attachment_metadata`, a Laravel session blob and a deeply
      nested array, each with a passing test.
- [x] A differential test round-trips real `serialize()` output for every PHP scalar and array
      shape.
- [x] Any line that cannot be covered is deleted, not annotated as ignored.

**Verification:**
- [x] `composer test -- --coverage --min=100`
- [x] `composer check`

**Dependencies:** T12 (`composer coverage` is already wired up)

**Files likely touched:**
- `tests/Fixtures/*`, `tests/Feature/*`, targeted `tests/Unit/*`

**Estimated scope:** Medium

---

## Task 14 — ✅ done: README, SECURITY.md, LICENSE.md, CHANGELOG.md

**Description:** The documentation a Packagist consumer needs before the first tag.

**Acceptance criteria:**
- [x] `README.md` covers installation, the four verbs, the builder, the exception hierarchy, the
      defaults table, and a worked `allowClasses()` example framed as a trust decision.
- [x] `SECURITY.md` states the five-point security model from `SPEC.md` and a private disclosure
      address.
- [x] `LICENSE.md` is MIT in your name; `CHANGELOG.md` has a `1.0.0` entry.
- [x] Every code sample in the README is copied from a passing test.

**Verification:**
- [x] Manual: run each README sample against the built package.
- [x] `composer validate --strict`
- [x] `composer check`

**Dependencies:** T13

**Files likely touched:**
- `README.md`, `SECURITY.md`, `LICENSE.md`, `CHANGELOG.md`

**Estimated scope:** Small

---

### Checkpoint E: ready to tag
- [x] All seven Success Criteria in `SPEC.md` met
- [x] `composer check` green; coverage at 100% of `src/`
- [x] `composer validate --strict` clean
- [x] **Review with human before tagging or publishing to Packagist**
