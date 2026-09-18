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
     * A float is NAN or infinite, neither of which JSON can express.
     */
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
