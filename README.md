# serialized

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

`unserialize()` tells you only `Error at offset 24`. This package tells you what is wrong, where,
and how to fix it:

```php
Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}');
```

```
String length mismatch at offset 18: declared 6 bytes, found 5.

a:1:{s:4:"name";s:6:"Chrom";}
                  ^

Fix: Change s:6 to s:5, or restore the missing bytes in the value.
```

Every exception carries a `Diagnostic` you can read programmatically, so a UI can highlight the
exact byte:

```php
try {
    Serialized::toJson($payload);
} catch (SerializedException $exception) {
    $diagnostic = $exception->diagnostic();

    $diagnostic?->offset;  // 18
    $diagnostic?->reason;  // 'String length mismatch at offset 18: declared 6 bytes, found 5.'
    $diagnostic?->fix;     // 'Change s:6 to s:5, or restore the missing bytes in the value.'
}
```

| Exception | Thrown when |
|---|---|
| `InvalidSerializedDataException` | Malformed payload: truncated token, length mismatch, unbalanced braces, wrong element count, trailing bytes |
| `UnsafeSerializedDataException` | An object of a class you have not allowed, or a reference (`R:`/`r:`) |
| `UnrepresentableValueException` | A value JSON cannot carry: a non-UTF-8 string, `NAN`, `INF`, `-INF` |
| `LimitExceededException` | `maxBytes`, `maxDepth` or `maxElements` exceeded |
| `JsonEncodingException` | `json_encode` failed despite validation |

All five implement `Serialized\Exceptions\SerializedException`, so one `catch` covers them all.
Catch a specific one and `diagnostic()` is guaranteed non-null.

## Objects are rejected by default

Unserializing an object can run its `__wakeup()` and `__destruct()` on data the payload controls —
the starting point of most PHP deserialization attacks. So objects are refused unless you name the
class yourself:

```php
Serialized::toJson('O:8:"stdClass":0:{}');
// UnsafeSerializedDataException: Payload contains an object of class "stdClass" at offset 0.

Serialized::make()->allowClasses([stdClass::class])->toJson('O:8:"stdClass":0:{}');
// {}
```

Allowing a class is a statement of trust about the payload, not just about the class. If an
attacker controls the payload and the class has a `__wakeup()` or `__destruct()` that touches the
filesystem, a database, or `unlink()`, allowing it hands them that method. Allow classes only for
payloads from a source you control.

The rejection happens *before* `unserialize()` is called, so a disallowed class is never
instantiated, and a `__PHP_Incomplete_Class` never reaches your code. See [SECURITY.md](SECURITY.md)
for the full model.

## Contributing

```bash
composer check      # formatting, static analysis and tests
composer coverage   # the suite with the 100% coverage gate
```

## License

MIT. See [LICENSE.md](LICENSE.md).
