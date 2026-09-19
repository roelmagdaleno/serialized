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
     * The payload names a class the caller allowed but PHP cannot load.
     */
    public static function unloadableClass(string $payload, int $offset, string $className): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'Class "%s" at offset %d is allowed but could not be loaded, so PHP would restore '
                .'it as a __PHP_Incomplete_Class rather than the class you allowed.',
                $className,
                $offset,
            ),
            fix: sprintf(
                'Make sure %s is autoloadable in this process, or correct the name passed to allowClasses().',
                $className,
            ),
        ));
    }

    /**
     * The payload names an allowed class PHP cannot build a value of.
     *
     * An abstract class, interface, trait or enum named by an object token makes
     * unserialize() raise a raw Error, so the payload is refused before it runs.
     */
    public static function unrestorableClass(string $payload, int $offset, string $className): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'Class "%s" at offset %d is allowed, but PHP cannot build a value of it: an object '
                .'token needs an instantiable class, and an enum token needs an enum.',
                $className,
                $offset,
            ),
            fix: sprintf(
                'Check the payload: %s is abstract, an interface, a trait, or an enum written as a '
                .'plain object, none of which can be restored.',
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
    public static function references(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'The payload contains a reference (R: or r:) at offset %d, which JSON cannot represent.',
                $offset,
            ),
            fix: 'Serialize a copy of the referenced value instead of a reference to it.',
        ));
    }
}
