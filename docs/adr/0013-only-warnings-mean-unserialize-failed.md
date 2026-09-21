# 0013. Only a warning means `unserialize()` failed

Date: 2026-09-19 · Status: accepted

## Context

`unserialize()` signals failure with `E_WARNING`, which is easy to miss and can leak into the
caller's error handler. `SafeUnserializer` therefore wraps the call in `set_error_handler`. Reading
*every* diagnostic as failure, however, rejects payloads PHP rebuilt perfectly well: an object
carrying a property its class no longer declares raises `E_DEPRECATED` ("Creation of dynamic
property") on PHP 8.2+. Those are exactly the legacy blobs this package exists to read — and
`isValid()`, which stops before `unserialize()`, called the same payload good.

## Decision

The handler treats only `E_WARNING` and `E_USER_WARNING` as failure and hands everything quieter
back to PHP. The previous handler is always restored in a `finally`. PHP's own message is carried
into the diagnostic rather than discarded.

## Consequences

- Legacy payloads with dynamic properties convert, and `toJson()` and `isValid()` agree.
- Notices and deprecations reach the application's error handling, where they belong.
- `phpunit.xml` sets `failOnWarning="true"`, so a warning leaking out of this stage fails the
  suite instead of passing quietly.
