<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use InvalidArgumentException;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Tokenizer\TokenType;

final class InvalidSerializedDataException extends InvalidArgumentException implements SerializedException
{
    use CarriesDiagnostic;

    /**
     * The payload has no bytes at all.
     */
    public static function emptyPayload(): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: '',
            offset: 0,
            reason: 'The payload is empty, so there is nothing to convert.',
            fix: 'Pass a serialized string, for example i:42; or a:0:{}.',
        ));
    }

    /**
     * A token starts with a byte that names no serialized type.
     */
    public static function unknownTypePrefix(string $payload, int $offset): self
    {
        $prefix = $payload[$offset];

        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('Unknown type prefix "%s" at offset %d.', $prefix, $offset),
            fix: 'Expected one of: N, b, i, d, s, a, O, C, R, r.',
        ));
    }

    /**
     * The payload ends while a token still expects more bytes.
     */
    public static function truncatedPayload(string $payload, string $expected): self
    {
        $offset = strlen($payload);

        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('Payload ends at offset %d while expecting "%s".', $offset, $expected),
            fix: sprintf('Append the missing "%s", or check whether the payload was cut short in storage.', $expected),
        ));
    }

    /**
     * A structural byte is not the one the format requires here.
     */
    public static function unexpectedByte(string $payload, int $offset, string $expected): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'Expected "%s" at offset %d, found "%s".',
                $expected,
                $offset,
                $payload[$offset],
            ),
            fix: sprintf('Replace the byte at offset %d with "%s".', $offset, $expected),
        ));
    }

    /**
     * A scalar literal does not match the syntax of the type that declared it.
     */
    public static function malformedValue(string $payload, int $offset, TokenType $type, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'Malformed %s literal "%s" at offset %d.',
                $type->label(),
                $literal,
                $offset,
            ),
            fix: sprintf('Write a valid %s value, or correct the type prefix.', $type->label()),
        ));
    }

    /**
     * The byte length of a string is not a non-negative integer.
     */
    public static function malformedLength(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('String length "%s" at offset %d is not a non-negative integer.', $literal, $offset),
            fix: 'Write the value\'s length in bytes, for example s:6:"Chrome";.',
        ));
    }

    /**
     * An array holds a different number of pairs than its header declares.
     */
    public static function elementCountMismatch(
        string $payload,
        int $offset,
        int $declaredCount,
        int $actualCount,
    ): self {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'The array at offset %d declares %d element(s) but holds %d.',
                $offset,
                $declaredCount,
                $actualCount,
            ),
            fix: sprintf('Change a:%d to a:%d, or correct the array contents.', $declaredCount, $actualCount),
        ));
    }

    /**
     * A closing brace appears where no array is open.
     */
    public static function unbalancedClose(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('The closing brace at offset %d closes an array that was never opened.', $offset),
            fix: 'Remove the brace, or add the array header it was meant to close.',
        ));
    }

    /**
     * An array is still open when the payload ends.
     */
    public static function unclosedArray(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('The array opened at offset %d is never closed.', $offset),
            fix: 'Append the missing closing brace, or check whether the payload was cut short in storage.',
        ));
    }

    /**
     * A value that PHP cannot use as an array key appears in a key position.
     */
    public static function nonScalarArrayKey(string $payload, int $offset, TokenType $type): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('A %s is used as an array key at offset %d.', $type->label(), $offset),
            fix: 'Array keys must be integers or strings; replace the key with one of those.',
        ));
    }

    /**
     * The element count of an array is not a non-negative integer.
     */
    public static function malformedElementCount(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('Array element count "%s" at offset %d is not a non-negative integer.', $literal, $offset),
            fix: 'Write the number of key/value pairs, for example a:2:{...}.',
        ));
    }

    /**
     * PHP's own unserialize() rejected a payload that passed validation.
     *
     * A backstop: the tokenizer is meant to catch these first, so reaching here means
     * the two disagree, and the payload is refused rather than trusted.
     */
    public static function rejectedByPhp(string $payload, string $phpMessage): self
    {
        preg_match('/offset (\d+)/', $phpMessage, $matches);
        $offset = min((int) ($matches[1] ?? 0), strlen($payload));

        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('PHP could not unserialize this payload at offset %d.', $offset),
            fix: 'Check the payload against the byte shown; it may have been altered in storage or transit.',
        ));
    }

    /**
     * The payload holds more bytes after its first complete value.
     */
    public static function trailingBytes(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf('The value ends before offset %d, but the payload continues.', $offset),
            fix: 'Remove the trailing bytes, or wrap the values in an array so the payload holds one value.',
        ));
    }

    /**
     * The declared byte length of a string or class name does not match the bytes present.
     *
     * @param  string|null  $prefix  the type letter to name in the fix, null for a generic wording
     */
    public static function lengthMismatch(
        string $payload,
        int $offset,
        int $declaredLength,
        int $actualLength,
        ?string $prefix = 's',
    ): self {
        return self::fromDiagnostic(new Diagnostic(
            payload: $payload,
            offset: $offset,
            reason: sprintf(
                'String length mismatch at offset %d: declared %d bytes, found %d.',
                $offset,
                $declaredLength,
                $actualLength,
            ),
            fix: $prefix === null
                ? sprintf(
                    'Change the declared length from %d to %d, or restore the missing bytes.',
                    $declaredLength,
                    $actualLength,
                )
                : sprintf(
                    'Change %1$s:%2$d to %1$s:%3$d, or restore the missing bytes in the value.',
                    $prefix,
                    $declaredLength,
                    $actualLength,
                ),
        ));
    }
}
