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
     * Checked before tokenizing, so an oversized payload costs one strlen() call.
     */
    public static function bytes(string $payload, int $actualBytes, int $configuredLimit): self
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
    public static function elements(string $payload, int $actualElements, int $configuredLimit): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: 0,
            reason: sprintf(
                'The payload holds %d elements, over the configured limit of %d.',
                $actualElements,
                $configuredLimit,
            ),
            fix: sprintf('Raise the limit with ->withMaxElements(%d), or convert less data.', $actualElements),
        ));
    }
}
