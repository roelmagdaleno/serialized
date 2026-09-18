<?php

declare(strict_types=1);

namespace Serialized\Diagnostics;

use InvalidArgumentException;

/**
 * Where a payload went wrong, why, and what to do about it.
 *
 * Consumers read these fields directly to highlight the offending byte, so the
 * offset is a byte position in the original payload, never a character position.
 */
final readonly class Diagnostic
{
    /**
     * @param  int  $offset  byte position of the problem, or one past the end when truncated
     */
    public function __construct(
        public string $payload,
        public int $offset,
        public string $reason,
        public string $fix,
    ) {
        if ($offset < 0) {
            throw new InvalidArgumentException("Diagnostic offset {$offset} cannot be negative.");
        }

        // One past the end is valid: a truncated payload fails where the next byte should be.
        $lastValidOffset = strlen($payload);

        if ($offset > $lastValidOffset) {
            throw new InvalidArgumentException(
                "Diagnostic offset {$offset} is beyond the payload, which is {$lastValidOffset} bytes.",
            );
        }
    }
}
