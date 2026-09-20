<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use InvalidArgumentException;
use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Tokenizer\Token;
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
            code: DiagnosticCode::EmptyPayload,
            payload: '',
            offset: 0,
        ));
    }

    /**
     * A token starts with a byte that names no serialized type.
     */
    public static function unknownTypePrefix(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnknownTypePrefix,
            payload: $payload,
            offset: $offset,
            context: ['prefix' => $payload[$offset]],
        ));
    }

    /**
     * The payload ends while a token still expects more bytes.
     */
    public static function truncatedPayload(string $payload, string $expected): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::TruncatedPayload,
            payload: $payload,
            offset: strlen($payload),
            context: ['expected' => $expected],
        ));
    }

    /**
     * A structural byte is not the one the format requires here.
     */
    public static function unexpectedByte(string $payload, int $offset, string $expected): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnexpectedByte,
            payload: $payload,
            offset: $offset,
            context: [
                'expected' => $expected,
                'found' => $payload[$offset],
            ],
        ));
    }

    /**
     * A scalar literal does not match the syntax of the type that declared it.
     */
    public static function malformedValue(string $payload, int $offset, TokenType $type, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MalformedValue,
            payload: $payload,
            offset: $offset,
            context: [
                'typeLabel' => $type->label(),
                'literal' => $literal,
            ],
        ));
    }

    /**
     * The byte length of a string is not a non-negative integer.
     */
    public static function malformedLength(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MalformedLength,
            payload: $payload,
            offset: $offset,
            context: ['literal' => $literal],
        ));
    }

    /**
     * A structure holds a different number of pairs than its header declares.
     */
    public static function elementCountMismatch(string $payload, Token $header, int $actualCount): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::ElementCountMismatch,
            payload: $payload,
            offset: $header->offset,
            context: [
                'structureLabel' => $header->type->label(),
                'className' => $header->className,
                'declaredCount' => $header->declaredCount ?? 0,
                'actualCount' => $actualCount,
            ],
        ));
    }

    /**
     * A closing brace appears where no array is open.
     */
    public static function unbalancedClose(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnbalancedClose,
            payload: $payload,
            offset: $offset,
        ));
    }

    /**
     * An array or object is still open when the payload ends.
     */
    public static function unclosedStructure(string $payload, Token $header): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::UnclosedStructure,
            payload: $payload,
            offset: $header->offset,
            context: ['structureLabel' => $header->type->label()],
        ));
    }

    /**
     * A value PHP cannot use as a key appears in a key slot.
     */
    public static function nonScalarKey(string $payload, Token $key, Token $header): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::NonScalarKey,
            payload: $payload,
            offset: $key->offset,
            context: [
                'keyTypeLabel' => $key->type->label(),
                'keySlot' => self::keySlotOf($header),
            ],
        ));
    }

    /**
     * Names the kind of slot a structure fills with its keys, article included.
     *
     * Only an object header carries a class name; an array header has none.
     */
    private static function keySlotOf(Token $header): string
    {
        return $header->className === null ? 'an array key' : 'a property name';
    }

    /**
     * The element count of an array is not a non-negative integer.
     */
    public static function malformedElementCount(string $payload, int $offset, string $literal): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::MalformedElementCount,
            payload: $payload,
            offset: $offset,
            context: ['literal' => $literal],
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
            code: DiagnosticCode::RejectedByPhp,
            payload: $payload,
            offset: $offset,
            context: ['phpMessage' => $phpMessage],
        ));
    }

    /**
     * A structure declared more pairs than the bytes left in the payload could hold.
     *
     * Checked before the count is used for anything: the smallest possible pair is four
     * bytes, so a larger count is unsatisfiable however the rest of the payload reads,
     * and an unchecked one saturates on casting and overflows when it is doubled.
     */
    public static function impossibleElementCount(
        string $payload,
        int $offset,
        string $declaredCount,
        int $remainingBytes,
    ): self {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::ImpossibleElementCount,
            payload: $payload,
            offset: $offset,
            context: [
                'declaredCount' => $declaredCount,
                'remainingByteCount' => $remainingBytes,
            ],
        ));
    }

    /**
     * A value reached past the end of the byte range its container declared.
     *
     * Only a nested body can hit this: it is how a payload would otherwise smuggle a
     * value past the length that decides how much of it unserialize() ever reads.
     */
    public static function valueOverrunsDeclaredLength(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::ValueOverrunsDeclaredLength,
            payload: $payload,
            offset: $offset,
        ));
    }

    /**
     * The payload holds more bytes after its first complete value.
     */
    public static function trailingBytes(string $payload, int $offset): self
    {
        return self::fromDiagnostic(new Diagnostic(
            code: DiagnosticCode::TrailingBytes,
            payload: $payload,
            offset: $offset,
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
            code: DiagnosticCode::LengthMismatch,
            payload: $payload,
            offset: $offset,
            context: [
                'declaredByteLength' => $declaredLength,
                'foundByteLength' => $actualLength,
                'prefix' => $prefix,
            ],
        ));
    }
}
