<?php

declare(strict_types=1);

namespace Serialized\Diagnostics;

/**
 * Draws the two-line snippet that points at the offending byte.
 *
 * Output is always pure ASCII — unprintable bytes become \xNN escapes and the
 * truncation marker is three dots — so a byte offset into the rendered line is
 * also its display column, whatever encoding the message is later printed in.
 */
final class SnippetRenderer
{
    private const int MAXIMUM_VISIBLE_BYTES = 60;

    private const string TRUNCATION_MARKER = '...';

    /**
     * Renders the payload line and the caret line that points at the offending byte.
     */
    public function render(Diagnostic $diagnostic): string
    {
        $payload = $diagnostic->payload;
        $payloadLength = strlen($payload);

        [$windowStart, $windowEnd] = $this->windowAround($diagnostic->offset, $payloadLength);

        $line = $this->escape(substr($payload, $windowStart, $windowEnd - $windowStart));
        $caretColumn = strlen($this->escape(substr($payload, $windowStart, $diagnostic->offset - $windowStart)));

        if ($windowStart > 0) {
            $line = self::TRUNCATION_MARKER.$line;
            $caretColumn += strlen(self::TRUNCATION_MARKER);
        }

        if ($windowEnd < $payloadLength) {
            $line .= self::TRUNCATION_MARKER;
        }

        return $line."\n".str_repeat(' ', $caretColumn).'^';
    }

    /**
     * @return array{int, int}
     */
    /**
     * Picks the slice of the payload to show, centred on the offset.
     *
     * @return array{int, int} the start and end byte offsets of the slice
     */
    private function windowAround(int $offset, int $payloadLength): array
    {
        if ($payloadLength <= self::MAXIMUM_VISIBLE_BYTES) {
            return [0, $payloadLength];
        }

        $start = max(0, $offset - intdiv(self::MAXIMUM_VISIBLE_BYTES, 2));
        $end = min($payloadLength, $start + self::MAXIMUM_VISIBLE_BYTES);

        return [max(0, $end - self::MAXIMUM_VISIBLE_BYTES), $end];
    }

    /**
     * Replaces every unprintable byte with a \xNN escape, keeping the output ASCII-only.
     */
    private function escape(string $bytes): string
    {
        $escaped = '';

        foreach (str_split($bytes) as $byte) {
            $escaped .= $this->isPrintable($byte) ? $byte : sprintf('\x%02x', ord($byte));
        }

        return $escaped;
    }

    /**
     * Tells whether a byte is printable ASCII and can be shown as itself.
     */
    private function isPrintable(string $byte): bool
    {
        return ord($byte) >= 0x20 && ord($byte) <= 0x7E;
    }
}
