# Serialized

Convert PHP serialized data into pretty-printed JSON — safely, with error messages you can act on.

```php
use Serialized\Serialized;

echo Serialized::toJson('a:2:{s:4:"name";s:6:"Chrome";s:6:"mobile";b:0;}');
```

```json
{
    "name": "Chrome",
    "mobile": false
}
```

## Installation

```bash
composer require roelmagdaleno/serialized
```

Requires PHP 8.4 or newer. No framework, no runtime dependencies.

## Usage

Four verbs cover everything:

```php
$json  = Serialized::toJson($payload);    // string, throws on any problem
$json  = Serialized::tryToJson($payload); // ?string, null instead of throwing
$value = Serialized::toArray($payload);   // mixed, the unserialized PHP value
$ok    = Serialized::isValid($payload);   // bool, never throws
```

Need to configure the conversion? `Serialized::make()` returns a converter you can tune. Every
method returns a new instance, so a configured converter is safe to share, cache, or bind once in
a container.

```php
$converter = Serialized::make()
    ->withMaxBytes(1_000_000)
    ->withMaxDepth(32)
    ->withMaxElements(50_000)
    ->allowClasses([App\Models\Money::class])
    ->compact();

$json = $converter->toJson($payload);
```

| Method | Default | What it does |
|---|---|---|
| `withMaxBytes()` | 16 MB | Rejects payloads larger than this, before reading them |
| `withMaxDepth()` | 64 | Rejects payloads nested deeper than this |
| `withMaxElements()` | 1,000,000 | Rejects payloads holding more values than this |
| `allowClasses()` | none | Permits objects of the given classes |
| `pretty()` / `compact()` | pretty | Multi-line or single-line JSON |
| `withJsonFlags()` | — | Adds `json_encode` flags on top of the defaults |

## Errors you can act on

When a payload is broken, `unserialize()` says `Error at offset 24` and leaves you there. This
package tells you what broke, where, and how to fix it:

```php
Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}');
```

```
String length mismatch at offset 18: declared 6 bytes, found 5.

a:1:{s:4:"name";s:6:"Chrom";}
                  ^

Fix: Change s:6 to s:5, or restore the missing bytes in the value.
```

The same information is available in code. Every exception carries a `Diagnostic`, so your app can
point at the exact byte too:

```php
try {
    Serialized::toJson($payload);
} catch (SerializedException $exception) {
    $diagnostic = $exception->diagnostic();

    $diagnostic?->code;    // DiagnosticCode::LengthMismatch
    $diagnostic?->offset;  // 18
    $diagnostic?->reason;  // 'String length mismatch at offset 18: declared 6 bytes, found 5.'
    $diagnostic?->fix;     // 'Change s:6 to s:5, or restore the missing bytes in the value.'
    $diagnostic?->context; // ['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's']
}
```

### Write your own messages

`reason` and `fix` are defaults, not a ceiling. `code` is a stable enum case — use it as a
translation key, a log label, or something to `match` on — and `context` holds the raw facts the
sentence was built from, so you can word the failure however your product needs:

```php
use Serialized\Diagnostics\DiagnosticCode;

$message = match ($diagnostic->code) {
    DiagnosticCode::LengthMismatch => __('errors.length', [
        'declared' => $diagnostic->context['declaredByteLength'],
        'found' => $diagnostic->context['foundByteLength'],
        'offset' => $diagnostic->offset,
    ]),
    DiagnosticCode::DisallowedClass => "Blocked: {$diagnostic->context['className']}",
    default => $diagnostic->reason,
};
```

Two small things: the byte `offset` never appears inside `context`, because the diagnostic already
owns it. And to print the package's own message again, caret snippet included, call
`DiagnosticMessage::render($diagnostic)`.

Here is every code and the context it carries:

| `DiagnosticCode` | `context` |
|---|---|
| `EmptyPayload` | — |
| `UnknownTypePrefix` | `prefix` |
| `TruncatedPayload` | `expected` |
| `UnexpectedByte` | `expected`, `found` |
| `MalformedValue` | `typeLabel`, `literal` |
| `MalformedLength` | `literal` |
| `MalformedElementCount` | `literal` |
| `ElementCountMismatch` | `structureLabel`, `className` (null for an array), `declaredCount`, `actualCount` |
| `ImpossibleElementCount` | `declaredCount`, `remainingByteCount` |
| `UnbalancedClose` | — |
| `UnclosedStructure` | `structureLabel` |
| `NonScalarKey` | `keyTypeLabel`, `keySlot` |
| `RejectedByPhp` | `phpMessage` |
| `ValueOverrunsDeclaredLength` | — |
| `TrailingBytes` | — |
| `LengthMismatch` | `declaredByteLength`, `foundByteLength`, `prefix` (null for a class name) |
| `DisallowedClass` | `className` |
| `UnloadableClass` | `className` |
| `UnrestorableClass` | `className` |
| `ContainsReference` | — |
| `NonUtf8String` | — |
| `NonBackedEnum` | `caseName` |
| `UnusablePropertyName` | — |
| `NonFiniteFloat` | `literal` |
| `MaxBytesExceeded` | `actualBytes`, `configuredLimit` |
| `MaxDepthExceeded` | `actualDepth`, `configuredLimit` |
| `MaxElementsExceeded` | `configuredLimit` |

### The five exceptions

| Exception | Thrown when |
|---|---|
| `InvalidSerializedDataException` | The payload is malformed: truncated, wrong length, unbalanced braces, wrong element count, trailing bytes |
| `UnsafeSerializedDataException` | An object of a class you have not allowed, a class PHP cannot load or restore, or a reference (`R:`/`r:`) |
| `UnrepresentableValueException` | A value JSON cannot carry: a non-UTF-8 string, `NAN`, `INF`, `-INF`, a non-backed enum case |
| `LimitExceededException` | `maxBytes`, `maxDepth` or `maxElements` was exceeded |
| `JsonEncodingException` | `json_encode` failed despite validation |

All five implement `Serialized\Exceptions\SerializedException`, so one `catch` covers them all.
Catch a specific one and `diagnostic()` is guaranteed non-null — except for
`JsonEncodingException`, the only one without a diagnostic: once the payload is validated, a
`json_encode` failure cannot be traced back to a byte.

## Objects are rejected by default

Unserializing an object can run its `__wakeup()` and `__destruct()` on data the payload controls.
That is where most PHP deserialization attacks begin, so objects are refused unless you name the
class yourself:

```php
Serialized::toJson('O:8:"stdClass":0:{}');
// UnsafeSerializedDataException: Payload contains an object of class "stdClass" at offset 0.

Serialized::make()->allowClasses([stdClass::class])->toJson('O:8:"stdClass":0:{}');
// {}
```

The full model is in [SECURITY.md](SECURITY.md).

### Private and protected properties survive

`json_encode()` only sees an object's public properties and drops the rest. This package converts
the object first, so nothing is lost:

```php
final class Money
{
    public function __construct(
        private int $amount = 5,
        protected string $currency = 'USD',
    ) {}
}

Serialized::make()->allowClasses([Money::class])->compact()->toJson(serialize(new Money));
// {"amount":5,"currency":"USD"}
```

Names come out as you declared them. The one exception is a name declared twice in the same
hierarchy — a class redeclaring a parent's private property — where both are kept and qualified as
`Parent::balance` and `Child::balance`, so neither value is lost.

None of this affects `toArray()`: it returns the real object, not a converted one.

## Contributing

```bash
composer check      # formatting, static analysis and tests
composer test -- --coverage --min=100   # the suite with the 100% coverage gate
```

## License

MIT. See [LICENSE.md](LICENSE.md).
