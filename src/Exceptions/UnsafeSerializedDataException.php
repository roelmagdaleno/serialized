<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;

final class UnsafeSerializedDataException extends RuntimeException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * The payload holds an object of a class the caller has not allow-listed.
     */
    public static function disallowedClass(string $payload, int $offset, string $className): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::DisallowedClass,
            payload: $payload,
            offset: $offset,
            context: ['className' => $className],
        ));
    }

    /**
     * The payload names a class the caller allowed but PHP cannot load.
     */
    public static function unloadableClass(string $payload, int $offset, string $className): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnloadableClass,
            payload: $payload,
            offset: $offset,
            context: ['className' => $className],
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
            code: DiagnosticCode::UnrestorableClass,
            payload: $payload,
            offset: $offset,
            context: ['className' => $className],
        ));
    }
}
