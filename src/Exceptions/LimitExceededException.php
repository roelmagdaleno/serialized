<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;

final class LimitExceededException extends RuntimeException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * The payload holds more bytes than the configured maximum.
     *
     * The diagnostic carries no payload: rendering a snippet of a payload rejected for its
     * size would copy the very bytes the limit exists to avoid handling.
     */
    public static function bytes(int $actualBytes, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MaxBytesExceeded,
            payload: '',
            offset: 0,
            context: [
                'actualBytes' => $actualBytes,
                'configuredLimit' => $configuredLimit,
            ],
        ));
    }

    /**
     * The payload nests deeper than the configured maximum.
     */
    public static function depth(string $payload, int $actualDepth, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MaxDepthExceeded,
            payload: $payload,
            offset: 0,
            context: [
                'actualDepth' => $actualDepth,
                'configuredLimit' => $configuredLimit,
            ],
        ));
    }

    /**
     * Lexing stopped at the element ceiling instead of counting the whole payload.
     *
     * The total is deliberately unknown: counting it would mean building the token stream
     * this limit exists to stop being built.
     */
    public static function elementCeiling(string $payload, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MaxElementsExceeded,
            payload: $payload,
            offset: 0,
            context: ['configuredLimit' => $configuredLimit],
        ));
    }
}
