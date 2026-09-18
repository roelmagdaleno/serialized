<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;

final class UnsafeSerializedDataException extends RuntimeException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * The payload holds an object of a class the caller has not allow-listed.
     */
    public static function disallowedClass(string $payload, int $offset, string $className): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'Payload contains an object of class "%s" at offset %d. Objects are rejected by '
                .'default because unserializing them can invoke __wakeup() and __destruct() on '
                .'attacker-controlled data.',
                $className,
                $offset,
            ),
            fix: sprintf(
                'If you trust this payload, allow the class explicitly: '
                .'Serialized::make()->allowClasses([%s::class]).',
                $className,
            ),
        ));
    }

    /**
     * The payload uses a back-reference, which JSON has no way to express.
     *
     * Resolving one by value would silently duplicate data, and a circular reference
     * cannot be resolved at all, so the payload is refused instead.
     */
    public static function references(string $payload): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: 0,
            reason: 'The payload contains a reference (R: or r:), which JSON cannot represent.',
            fix: 'Serialize a copy of the referenced value instead of a reference to it.',
        ));
    }
}
