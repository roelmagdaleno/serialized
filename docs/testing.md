# Testing

Pest 5. `tests/Unit` mirrors `src/`, one file per class; `tests/Feature` covers the public API end
to end; `tests/Fixtures` holds real payloads — the Chrome example, WordPress
`_wp_attachment_metadata`, a Laravel session blob, a deeply nested array.

```bash
composer test       # vendor/bin/pest
composer test -- --coverage --min=100     # the suite with the coverage gate
composer check      # pint --test && stan && test — the full gate
```

## Rules

- **Every behaviour change is test-first**: red, green, refactor.
- **100% line coverage of `src/`.** The package is small, pure and has no I/O, so a missing branch
  means an untested security boundary. The threshold is never lowered to reach green.
- **Never weaken, skip or delete a test** to get to green. Fix the code, or raise it.
- **Assert on the `Diagnostic`** — `code`, `offset`, `context` — not on the full message string, so
  wording can change without breaking the suite.
- **Every rejection test asserts both** that the package throws *and* that `unserialize()` also
  fails on that payload, so the tokenizer can never drift from PHP's own parser. Acceptance tests
  round-trip through real `serialize()` output.
- `phpunit.xml` sets `failOnWarning="true"`, so a warning leaking out of `SafeUnserializer` fails
  the suite rather than passing quietly.

## Cases that must stay covered

| Area | Cases |
|---|---|
| Happy path | The Chrome payload; nested arrays; every scalar type; empty array; empty string; integer and string keys |
| Malformed | Truncated payload; `s:6` for a 5-byte string; unbalanced `{`/`}`; wrong element count in an array or an object, each named with its own header spelling; trailing bytes after a complete value; unknown type prefix; empty input |
| Tokenizer correctness | `s:5:"a";b";` tokenizes as one string containing `a";b` — slicing by declared byte length, never scanning for the closing quote; a multibyte string tokenizes by bytes |
| Security | `O:` object rejected by default; accepted when allow-listed; `C:` custom object; nested object inside an allowed array; `R:`/`r:` reference rejected; an allow-listed class PHP cannot load, and one it cannot instantiate, both rejected; an allow-list entry spelled `\Class`, `CLASS` or `\CLASS` honoured, with no `__PHP_Incomplete_Class_Name` in the output |
| `C:` bodies | A body holding `R:`/`r:` rejected; a body naming a class not on the allow-list rejected; a body deeper than the remaining depth budget, and one larger than the remaining element budget, rejected; a non-UTF-8 string and a `NAN` inside a body rejected with a byte offset into the whole payload; a body that is not a complete serialized value rejected; a well-formed body still converts |
| Property names | `\0` alone, `\0ab` and `\0A\0b\0c` as property names rejected with the name's byte offset, rather than an `Error` out of `toJson()`; a real private and a real protected name still convert; a NUL inside an array key or a string value still converts |
| Normalization | Private and protected properties appear in the JSON; a parent/child private collision yields `Parent::x` and `Child::x`; an object with only numeric property names stays a JSON object; a backed enum stays its value; an object nested in an array is normalized too |
| Limits | Payload over `maxBytes`; nesting over `maxDepth`; element count over `maxElements`; each limit raised via the builder and then passing; a payload whose token count passes `maxElements` is refused while lexing, reporting the ceiling rather than a total it never counted; a 500-deep payload throws rather than exhausting the stack |
| Impossible counts | An array or object header declaring more pairs than the bytes that remain could hold, at the saturating boundary (`2^62`) and far past it, rejected as malformed rather than overflowing |
| Unrepresentable | Non-UTF-8 byte in a string; `d:NAN;`; `d:INF;`; `d:-INF;`; `d:1e999;` and `d:-1e999;`, which overflow to infinity rather than spelling it; a non-backed enum case, rejected with a byte offset rather than reaching `JsonEncodingException` |
| Dynamic properties | An object carrying a property its class no longer declares still converts — PHP's `E_DEPRECATED` is not a failure |
| Diagnostics | Offset is byte-accurate; caret sits under the offending byte; `fix` is present and non-generic |
| API | `tryToJson` returns `null` instead of throwing; `isValid` never throws; `toArray` returns the PHP value with its objects intact; builder methods return new instances and leave the original unchanged |
