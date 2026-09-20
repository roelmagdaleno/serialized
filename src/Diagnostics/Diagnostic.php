<?php

declare(strict_types=1);

namespace Serialized\Diagnostics;

use InvalidArgumentException;

/**
 * Where a payload went wrong, why, and what to do about it.
 *
 * Consumers read these fields directly to highlight the offending byte, so the
 * offset is a byte position in the original payload, never a character position.
 *
 * The `reason` and `fix` sentences are derived from the code rather than passed in,
 * so a diagnostic can never describe one failure while naming another. A consumer
 * that wants its own wording reads `code` and `context` and ignores both sentences.
 */
final readonly class Diagnostic
{
    public string $reason;

    public string $fix;

    /**
     * @param  int  $offset  byte position of the problem, or one past the end when truncated
     * @param  array<string, string|int|bool|null>  $context  the facts behind the failure, keyed per code
     */
    public function __construct(
        public DiagnosticCode $code,
        public string $payload,
        public int $offset,
        public array $context = [],
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

        $this->reason = $code->reason($offset, $context);
        $this->fix = $code->fix($offset, $context);
    }
}
