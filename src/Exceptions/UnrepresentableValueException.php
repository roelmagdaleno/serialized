<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;

final class UnrepresentableValueException extends RuntimeException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * A string holds bytes that are not valid UTF-8, which JSON cannot carry.
     */
    public static function nonUtf8String(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('Non-UTF-8 bytes in the string at offset %d. JSON requires valid UTF-8.', $offset),
            fix: 'Base64-encode this value before serializing it, or repair its encoding.',
        ));
    }

    /**
     * A non-backed enum case has no value to stand in for it in JSON.
     */
    public static function nonBackedEnum(string $payload, int $offset, string $caseName): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'The enum case %s at offset %d is not backed, so it has no JSON representation.',
                $caseName,
                $offset,
            ),
            fix: 'Give the enum a backing type, or replace the case with a string before serializing.',
        ));
    }

    /**
     * A float is NAN or infinite, neither of which JSON can express.
     */
    /**
     * A property name holds a NUL byte that demangling does not remove.
     *
     * PHP can hold such a property but cannot assign it to a stdClass, and json_encode()
     * drops it without a word, so the payload is refused rather than quietly shortened.
     */
    public static function unusablePropertyName(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'The property name at offset %d holds a NUL byte that is not PHP\'s private or protected spelling.',
                $offset,
            ),
            fix: 'Remove the NUL byte from the property name, or drop the property before serializing.',
        ));
    }

    public static function nonFiniteFloat(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('Float %s at offset %d has no JSON representation.', $literal, $offset),
            fix: 'Replace the value with null, a string, or a finite number before serializing.',
        ));
    }
}
