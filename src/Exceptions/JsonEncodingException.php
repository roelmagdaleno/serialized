<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use JsonException;
use RuntimeException;
use Serialized\Diagnostics\Diagnostic;

/**
 * Validation should catch everything json_encode() rejects, so this is a backstop
 * rather than a path callers are expected to hit.
 */
final class JsonEncodingException extends RuntimeException implements SerializedException
{
    /**
     * json_encode() rejected a value that validation was expected to have caught.
     */
    public static function encodingFailed(JsonException $previous): self
    {
        return new self(
            sprintf('Encoding the unserialized value as JSON failed: %s', $previous->getMessage()),
            previous: $previous,
        );
    }

    /**
     * Always null: an encoding failure cannot be traced to a byte in the payload.
     */
    public function diagnostic(): ?Diagnostic
    {
        return null;
    }
}
