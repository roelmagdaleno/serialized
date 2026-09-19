# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org).

## Unreleased

### Changed

- `Token` keeps offsets into the payload rather than copies of its bytes; `raw` and `literal` are
  now methods deriving their substring on demand, and the redundant `declaredLength` is gone.
  Peak memory falls by about 20% on a string-heavy payload and 11% on one of minimal tokens.

### Fixed

- A serialized object carrying a property its class no longer declares now converts. PHP raises
  `E_DEPRECATED` ("Creation of dynamic property") for it on 8.2+, which the error handler around
  `unserialize()` read as failure — so `toJson()` called a payload PHP had just rebuilt malformed,
  reported it at offset 0, and disagreed with `isValid()`, which said the same payload was fine.
  Only `E_WARNING` and `E_USER_WARNING` now mean failure, and the diagnostic carries PHP's own
  message instead of discarding it.
- A property name holding a NUL byte that demangling does not remove — `"\0"`, `"\0ab"`,
  `"\0A\0b\0c"` — is refused with the name's byte offset. It previously threw
  `Error: Cannot access property starting with "\0"` out of `toJson()`, and building the object
  from an array instead would have let `json_encode()` drop the property silently.
- A float literal that overflows the double range (`d:1e999;`, `d:1e309;`) is rejected as
  unrepresentable with a byte offset. Only the spellings `NAN`, `INF` and `-INF` were matched, so
  an overflowing literal reached `json_encode()` and surfaced as `JsonEncodingException`, whose
  `diagnostic()` is null.

### Security

_No release is tagged yet, so these carry no upgrade path; `LimitExceededException::elements()`
was removed in favour of `::elementCeiling()`, which reports the ceiling rather than a total the
tokenizer deliberately never counts._

- An allow-list entry spelled with a leading separator (`'\App\Models\User'`) no longer fails
  open. The caller's spelling was passed to `unserialize()` verbatim, which matches
  `allowed_classes` case-insensitively but does not strip the separator, so the class was refused
  by PHP after the package had allowed it and `__PHP_Incomplete_Class_Name` reached the output.
  `ClassAllowList` now owns the one spelling that reaches PHP.
- `maxElements` is enforced by the tokenizer as it lexes, so a payload under `maxBytes` can no
  longer be an out-of-memory kill. A 13.7 MB payload of four-byte tokens peaked at 894 MB and
  fatally exhausted a 256 MB process — inside `tryToJson()`, whose contract is that it never
  throws — because 4M `Token` objects were built before anything counted them.
- An array or object header declaring more pairs than the bytes that remain could hold is refused
  as malformed. A count at or past `2^62` saturated on casting and then overflowed to a float when
  doubled, throwing a raw `TypeError` out of `toJson()` and `toArray()` from a 24-byte payload.
- A custom-serialized (`C:`) body is validated as a payload in its own right instead of being
  passed through opaque. A body could previously smuggle an `R:`/`r:` reference past the policy —
  producing a cyclic value that exhausted the stack in `ValueNormalizer` and threw a raw `Error` —
  and could also name classes the allow-list never saw, bypass `maxDepth` and `maxElements`, and
  carry non-UTF-8 strings and `NAN` that surfaced as a `JsonEncodingException` with no offset.

## 1.0.0

First release.

- `Serialized::toJson()`, `tryToJson()`, `toArray()` and `isValid()`.
- `Serialized::make()` returns an immutable, fluent `SerializedConverter`.
- Objects, enums and custom-serialized objects are rejected unless allow-listed with
  `allowClasses()`; the rejection happens before `unserialize()` is called.
- Allow-listed objects convert with every property, private and protected included; a property
  name declared twice in one hierarchy is qualified as `Class::name` rather than overwritten.
- An allow-listed class PHP cannot load or cannot restore — an unknown name, an abstract class,
  an interface, an enum written as an object — is rejected instead of producing a
  `__PHP_Incomplete_Class` or a raw `Error`.
- References (`R:`/`r:`) and values JSON cannot carry — non-UTF-8 strings, `NAN`, `INF`, `-INF`,
  non-backed enum cases — are rejected with a byte-accurate diagnostic.
- A wrong element count or a missing brace is reported against the structure that owns it: an
  object is called an object, and the suggested fix spells `O:8:"stdClass":1`, not `a:1`.
- Configurable limits: 16 MB, depth 64, 1,000,000 elements.
- Every exception carries a `Diagnostic` with the offset, the reason and a suggested fix.
