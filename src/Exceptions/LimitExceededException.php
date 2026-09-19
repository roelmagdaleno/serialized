<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use RuntimeException;
use Serialized\Diagnostics\Diagnostic;

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
            payload: '',
            offset: 0,
            reason: sprintf(
                'The payload is %d bytes, over the configured limit of %d.',
                $actualBytes,
                $configuredLimit,
            ),
            fix: sprintf('Raise the limit with ->withMaxBytes(%d), or convert a smaller payload.', $actualBytes),
        ));
    }

    /**
     * The payload nests deeper than the configured maximum.
     */
    public static function depth(string $payload, int $actualDepth, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: 0,
            reason: sprintf(
                'The payload nests %d levels deep, over the configured limit of %d.',
                $actualDepth,
                $configuredLimit,
            ),
            fix: sprintf('Raise the limit with ->withMaxDepth(%d), or flatten the payload.', $actualDepth),
        ));
    }

    /**
     * The payload holds more values than the configured maximum.
     */
    /**
     * Lexing stopped at the element ceiling instead of counting the whole payload.
     *
     * The total is deliberately unknown: counting it would mean building the token stream
     * this limit exists to stop being built.
     */
    public static function elementCeiling(string $payload, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: 0,
            reason: sprintf(
                'The payload holds more than %d elements, the configured limit.',
                $configuredLimit,
            ),
            fix: 'Raise the limit with ->withMaxElements(), or convert less data.',
        ));
    }
}
