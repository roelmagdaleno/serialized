# Architecture Decision Records

One file per decision that has a rejected alternative worth remembering. Each records the context
that forced the choice, the choice, and what it costs. They are **immutable**: a decision that is
later reversed gets a new record that supersedes the old one, rather than an edit.

Everything that is simply true about the package today — the pipeline, the stage boundaries, the
project layout — belongs in [`docs/architecture.md`](../architecture.md), not here.

| # | Decision |
|---|---|
| [0001](0001-tokenize-before-unserialize.md) | Tokenize and validate before calling `unserialize()` |
| [0002](0002-slice-strings-by-declared-length.md) | Slice strings by declared byte length, never by scanning |
| [0003](0003-library-packaging-choices.md) | Package as a library: no `version` field, no lock file, no CI |
| [0004](0004-trytojson-catches-throwable.md) | `tryToJson()` catches `Throwable`, not just `SerializedException` |
| [0005](0005-normalize-objects-to-stdclass.md) | Normalize objects to `stdClass` before encoding |
| [0006](0006-qualify-colliding-property-names.md) | Qualify colliding property names as `Class::name`, never drop one |
| [0007](0007-omit-class-names-from-output.md) | Keep class names out of the JSON output |
| [0008](0008-reject-non-backed-enums.md) | Reject non-backed enum cases rather than coercing them |
| [0009](0009-normalized-allow-list-spelling.md) | Pass a normalized allow-list spelling to `unserialize()` |
| [0010](0010-validate-custom-serialized-bodies.md) | Validate a `C:` body as a payload in its own right |
| [0011](0011-reject-unusable-property-names.md) | Reject property names that do not survive demangling |
| [0012](0012-reject-non-finite-floats.md) | Reject a float by its value, not by its spelling |
| [0013](0013-only-warnings-mean-unserialize-failed.md) | Only a warning means `unserialize()` failed |
| [0014](0014-enforce-max-elements-while-lexing.md) | Enforce `maxElements` in the tokenizer, and keep offsets in `Token` |
| [0015](0015-check-class-restorability.md) | Allowing a class is honoured only if PHP can actually restore it |
| [0016](0016-diagnostic-code-owns-wording.md) | `DiagnosticCode` owns the default wording |
| [0017](0017-rewrite-escaped-strings-before-unserialize.md) | Rewrite `S:` escaped strings to `s:` before `unserialize()` |
