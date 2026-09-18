# Changelog

All notable changes to this project are documented here. This project follows
[Semantic Versioning](https://semver.org).

## 1.0.0

First release.

- `Serialized::toJson()`, `tryToJson()`, `toArray()` and `isValid()`.
- `Serialized::make()` returns an immutable, fluent `SerializedConverter`.
- Objects, enums and custom-serialized objects are rejected unless allow-listed with
  `allowClasses()`; the rejection happens before `unserialize()` is called.
- References (`R:`/`r:`) and values JSON cannot carry — non-UTF-8 strings, `NAN`, `INF`, `-INF` —
  are rejected with a byte-accurate diagnostic.
- Configurable limits: 16 MB, depth 64, 1,000,000 elements.
- Every exception carries a `Diagnostic` with the offset, the reason and a suggested fix.
