<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;

final class UnrepresentableValueException extends RuntimeException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * A string holds bytes that are not valid UTF-8, which JSON cannot carry.
     */
    public static function nonUtf8String(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::NonUtf8String,
            payload: $payload,
            offset: $offset,
        ));
    }

    /**
     * A non-backed enum case has no value to stand in for it in JSON.
     */
    public static function nonBackedEnum(string $payload, int $offset, string $caseName): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::NonBackedEnum,
            payload: $payload,
            offset: $offset,
            context: ['caseName' => $caseName],
        ));
    }

    /**
     * A property name holds a NUL byte that demangling does not remove.
     *
     * PHP can hold such a property but cannot assign it to a stdClass, and json_encode()
     * drops it without a word, so the payload is refused rather than quietly shortened.
     */
    public static function unusablePropertyName(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnusablePropertyName,
            payload: $payload,
            offset: $offset,
        ));
    }

    /**
     * A float is NAN or infinite, neither of which JSON can express.
     */
    public static function nonFiniteFloat(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::NonFiniteFloat,
            payload: $payload,
            offset: $offset,
            context: ['literal' => $literal],
        ));
    }
}
