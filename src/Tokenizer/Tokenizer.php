<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Exceptions\LimitExceededException;

/**
 * Lexes a serialized payload into a flat stream of tokens.
 *
 * The tokenizer decides whether unserialize() may safely be called; it never
 * produces PHP values of its own. Structure is the parser's job — this class only
 * guarantees that each token is individually well formed.
 */
final class Tokenizer
{
    /**
     * Lexes a byte range of the payload, left to right.
     *
     * The range defaults to the whole payload. A custom-serialized body is lexed in
     * place instead of as a string of its own, so every offset a diagnostic reports is
     * a byte of the payload the caller passed rather than of a slice they never saw.
     *
     * Elements are counted as they are lexed and the ceiling stops the loop, because a
     * Token costs far more memory than the bytes it was read from: counting them only
     * once the stream is complete makes a payload well under maxBytes an out-of-memory
     * kill rather than a limit. Closing braces are not elements, which is what keeps this
     * count the same one the parser reports.
     *
     * @param  int|null  $through  byte position to stop at, null for the end of the payload
     * @param  int|null  $maxElements  how many elements may still be lexed, null for no ceiling
     * @return list<Token>
     *
     * @throws InvalidSerializedDataException when any token is malformed or overruns the range
     * @throws LimitExceededException when the range holds more elements than the ceiling allows
     */
    public function tokenize(string $payload, int $from = 0, ?int $through = null, ?int $maxElements = null): array
    {
        $end = $through ?? strlen($payload);

        if ($from >= $end) {
            throw InvalidSerializedDataException::emptyPayload();
        }

        $tokens = [];
        $cursor = $from;
        $elements = 0;

        while ($cursor < $end) {
            $token = $this->readToken($payload, $cursor);

            if ($token->type !== TokenType::Close && $maxElements !== null && ++$elements > $maxElements) {
                throw LimitExceededException::elementCeiling($payload, $maxElements);
            }

            $tokens[] = $token;
            $cursor = $token->endOffset();
        }

        if ($cursor > $end) {
            throw InvalidSerializedDataException::valueOverrunsDeclaredLength($payload, $end);
        }

        return $tokens;
    }

    /**
     * Reads the single token that starts at the given offset.
     */
    private function readToken(string $payload, int $offset): Token
    {
        return match ($payload[$offset]) {
            'N' => $this->readNull($payload, $offset),
            'b' => $this->readScalar($payload, $offset, TokenType::Boolean, $this->isValidBoolean(...)),
            'i' => $this->readScalar($payload, $offset, TokenType::Integer, $this->isValidInteger(...)),
            'd' => $this->readScalar($payload, $offset, TokenType::Float, $this->isValidFloat(...)),
            's' => $this->readString($payload, $offset),
            'S' => $this->readEscapedString($payload, $offset),
            'a' => $this->readArrayHeader($payload, $offset),
            'O' => $this->readObjectHeader($payload, $offset),
            'C' => $this->readCustomObject($payload, $offset),
            'R' => $this->readScalar($payload, $offset, TokenType::Reference, $this->isValidInteger(...)),
            'r' => $this->readScalar($payload, $offset, TokenType::ValueReference, $this->isValidInteger(...)),
            'E' => $this->readEnum($payload, $offset),
            '}' => new Token(TokenType::Close, $payload, $offset, length: 1),
            default => throw InvalidSerializedDataException::unknownTypePrefix($payload, $offset),
        };
    }

    /**
     * Reads `N;`, the only token with no value of its own.
     */
    private function readNull(string $payload, int $offset): Token
    {
        $this->expectByte($payload, $offset + 1, ';');

        return new Token(TokenType::Null, $payload, $offset, length: 2);
    }

    /**
     * Reads a `prefix:literal;` token and validates the literal for its type.
     *
     * @param  callable(string): bool  $isValidLiteral
     */
    private function readScalar(string $payload, int $offset, TokenType $type, callable $isValidLiteral): Token
    {
        $this->expectByte($payload, $offset + 1, ':');

        $literalStart = $offset + 2;
        $terminator = $this->findSequence($payload, ';', $literalStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ';');

        $literal = substr($payload, $literalStart, $terminator - $literalStart);

        if (! $isValidLiteral($literal)) {
            throw InvalidSerializedDataException::malformedValue($payload, $literalStart, $type, $literal);
        }

        return new Token(
            $type,
            $payload,
            $offset,
            length: $terminator + 1 - $offset,
            literalOffset: $literalStart,
            literalLength: $terminator - $literalStart,
        );
    }

    /**
     * Reads `s:LENGTH:"VALUE";`, slicing the value by its declared byte length.
     *
     * The declared length is authoritative: a serialized string may legally contain
     * quotes, semicolons and NUL bytes, so scanning for the closing quote would
     * truncate the value and let crafted payloads past the tokenizer.
     */
    private function readString(string $payload, int $offset): Token
    {
        $this->expectByte($payload, $offset + 1, ':');

        $lengthStart = $offset + 2;
        $lengthEnd = $this->findSequence($payload, ':', $lengthStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $declaredLength = $this->readDeclaredLength($payload, $lengthStart, $lengthEnd);

        $this->expectByte($payload, $lengthEnd + 1, '"');

        $valueStart = $lengthEnd + 2;
        $availableBytes = strlen($payload) - $valueStart;

        if ($declaredLength > $availableBytes) {
            throw InvalidSerializedDataException::lengthMismatch(
                $payload,
                $lengthStart,
                $declaredLength,
                $this->actualLengthOf($payload, $valueStart),
            );
        }

        $closingQuote = $valueStart + $declaredLength;

        if (($payload[$closingQuote] ?? null) !== '"') {
            $this->rejectLengthOrQuote($payload, $lengthStart, $valueStart, $declaredLength, $closingQuote);
        }

        $this->expectByte($payload, $closingQuote + 1, ';');

        return new Token(
            TokenType::String,
            $payload,
            $offset,
            length: $closingQuote + 2 - $offset,
            literalOffset: $valueStart,
            literalLength: $declaredLength,
        );
    }

    /**
     * Reads `S:LENGTH:"VALUE";`, whose value spells its bytes as `\XX` escapes.
     *
     * PHP writes this form when a string holds bytes it would rather not print, and reads
     * it back as an ordinary string. The declared length counts the bytes it spells, not
     * the bytes on the page, so the value's raw span is found by decoding rather than by
     * arithmetic -- and the token carries what the escapes spell, because that is the
     * value every later stage has to judge.
     */
    private function readEscapedString(string $payload, int $offset): Token
    {
        $this->expectByte($payload, $offset + 1, ':');

        $lengthStart = $offset + 2;
        $lengthEnd = $this->findSequence($payload, ':', $lengthStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $declaredLength = $this->readDeclaredLength($payload, $lengthStart, $lengthEnd);

        $this->expectByte($payload, $lengthEnd + 1, '"');

        $valueStart = $lengthEnd + 2;
        $decoded = '';
        $cursor = $valueStart;

        while (strlen($decoded) < $declaredLength) {
            if ($cursor >= strlen($payload)) {
                throw InvalidSerializedDataException::lengthMismatch(
                    $payload,
                    $lengthStart,
                    $declaredLength,
                    strlen($decoded),
                    prefix: 'S',
                );
            }

            $decoded .= $this->readEscapedByte($payload, $cursor);
            $cursor += $payload[$cursor] === '\\' ? 3 : 1;
        }

        if (($payload[$cursor] ?? null) !== '"') {
            $this->rejectEscapedLengthOrQuote($payload, $lengthStart, $valueStart, $declaredLength, $cursor);
        }

        $this->expectByte($payload, $cursor + 1, ';');

        return new Token(
            TokenType::String,
            $payload,
            $offset,
            length: $cursor + 2 - $offset,
            literalOffset: $valueStart,
            literalLength: $cursor - $valueStart,
            decodedLiteral: $decoded,
        );
    }

    /**
     * Reads the one byte written at the cursor, resolving a `\XX` escape when it finds one.
     */
    private function readEscapedByte(string $payload, int $cursor): string
    {
        if ($payload[$cursor] !== '\\') {
            return $payload[$cursor];
        }

        $escape = substr($payload, $cursor + 1, 2);

        if (preg_match('/^[0-9A-Fa-f]{2}$/', $escape) !== 1) {
            throw InvalidSerializedDataException::malformedValue(
                $payload,
                $cursor,
                TokenType::String,
                substr($payload, $cursor, 3),
            );
        }

        return chr((int) hexdec($escape));
    }

    /**
     * Blames the declared length when the real terminator can be found, the missing quote
     * otherwise, counting what the escapes between here and there actually spell.
     */
    private function rejectEscapedLengthOrQuote(
        string $payload,
        int $lengthStart,
        int $valueStart,
        int $declaredLength,
        int $closingQuote,
    ): never {
        $terminator = $this->findSequence($payload, '";', $valueStart);

        if ($terminator === null) {
            $this->expectByte($payload, $closingQuote, '"');
        }

        $decoded = '';
        $cursor = $valueStart;

        while ($cursor < $terminator) {
            $decoded .= $this->readEscapedByte($payload, $cursor);
            $cursor += $payload[$cursor] === '\\' ? 3 : 1;
        }

        throw InvalidSerializedDataException::lengthMismatch(
            $payload,
            $lengthStart,
            $declaredLength,
            strlen($decoded),
            prefix: 'S',
        );
    }

    /**
     * Reads `a:COUNT:{`, the header that opens an array.
     *
     * Only the header is a token: the contents and the closing brace are lexed as
     * tokens of their own, leaving the nesting for the parser to check.
     */
    private function readArrayHeader(string $payload, int $offset): Token
    {
        $this->expectByte($payload, $offset + 1, ':');

        $countStart = $offset + 2;
        $countEnd = $this->findSequence($payload, ':', $countStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $this->expectByte($payload, $countEnd + 1, '{');

        $declaredCount = $this->readDeclaredCount($payload, $countStart, $countEnd);

        return new Token(
            TokenType::Array,
            $payload,
            $offset,
            length: $countEnd + 2 - $offset,
            declaredCount: $declaredCount,
        );
    }

    /**
     * Reads `O:LEN:"Class":COUNT:{`, the header that opens an object.
     *
     * Recognition only — whether the class may be unserialized is the policy's call.
     */
    private function readObjectHeader(string $payload, int $offset): Token
    {
        [$className, $afterClassName] = $this->readClassName($payload, $offset);

        $countStart = $afterClassName + 1;
        $countEnd = $this->findSequence($payload, ':', $countStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $this->expectByte($payload, $countEnd + 1, '{');

        $declaredCount = $this->readDeclaredCount($payload, $countStart, $countEnd);

        return new Token(
            TokenType::Object,
            $payload,
            $offset,
            length: $countEnd + 2 - $offset,
            declaredCount: $declaredCount,
            className: $className,
        );
    }

    /**
     * Reads `C:LEN:"Class":LEN:{data}`, an object that serialized itself.
     *
     * Its body is opaque to us, so it is lexed as one token and left to the policy.
     */
    private function readCustomObject(string $payload, int $offset): Token
    {
        [$className, $afterClassName] = $this->readClassName($payload, $offset);

        $lengthStart = $afterClassName + 1;
        $lengthEnd = $this->findSequence($payload, ':', $lengthStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $declaredLength = $this->readDeclaredLength($payload, $lengthStart, $lengthEnd);

        $this->expectByte($payload, $lengthEnd + 1, '{');

        $bodyStart = $lengthEnd + 2;

        if ($declaredLength > strlen($payload) - $bodyStart) {
            throw InvalidSerializedDataException::lengthMismatch(
                $payload,
                $lengthStart,
                $declaredLength,
                strlen($payload) - $bodyStart,
                null,
            );
        }

        $this->expectByte($payload, $bodyStart + $declaredLength, '}');

        return new Token(
            TokenType::CustomObject,
            $payload,
            $offset,
            length: $bodyStart + $declaredLength + 1 - $offset,
            literalOffset: $bodyStart,
            literalLength: $declaredLength,
            className: $className,
        );
    }

    /**
     * Reads `E:LEN:"Enum:Case";`, the serialized form of an enum case.
     *
     * The enum class is recorded separately from the case so the allow-list governs
     * enums too: restoring one instantiates a class just as an object token does.
     */
    private function readEnum(string $payload, int $offset): Token
    {
        [$caseName, $afterCaseName] = $this->readClassName($payload, $offset);

        $this->expectByte($payload, $afterCaseName, ';');

        $separator = strpos($caseName, ':');

        return new Token(
            TokenType::Enum,
            $payload,
            $offset,
            length: $afterCaseName + 1 - $offset,
            literalOffset: $afterCaseName - 1 - strlen($caseName),
            literalLength: strlen($caseName),
            className: $separator === false ? $caseName : substr($caseName, 0, $separator),
        );
    }

    /**
     * Reads the `LEN:"Name"` part shared by object, custom object and enum headers.
     *
     * @return array{string, int} the name and the offset of the byte after its quote
     */
    private function readClassName(string $payload, int $offset): array
    {
        $this->expectByte($payload, $offset + 1, ':');

        $lengthStart = $offset + 2;
        $lengthEnd = $this->findSequence($payload, ':', $lengthStart)
            ?? throw InvalidSerializedDataException::truncatedPayload($payload, ':');

        $declaredLength = $this->readDeclaredLength($payload, $lengthStart, $lengthEnd);

        $this->expectByte($payload, $lengthEnd + 1, '"');

        $nameStart = $lengthEnd + 2;
        $closingQuote = $nameStart + $declaredLength;

        if (($payload[$closingQuote] ?? null) !== '"') {
            throw InvalidSerializedDataException::lengthMismatch(
                $payload,
                $lengthStart,
                $declaredLength,
                ($this->findSequence($payload, '"', $nameStart) ?? $nameStart) - $nameStart,
                $payload[$offset],
            );
        }

        return [substr($payload, $nameStart, $declaredLength), $closingQuote + 1];
    }

    /**
     * Parses the pair count an array or object header declares.
     *
     * Called once the opening brace is known to be there, so the bytes that remain are
     * the bytes the contents could use. A count larger than that is refused rather than
     * carried forward: the cast saturates at PHP_INT_MAX, which the parser then doubles into a
     * float and cannot store. The bound is deliberately the loosest one that holds — a
     * pair cannot occupy less than a byte — so that a count which is merely wrong still
     * reaches the parser, which knows how many pairs the structure really has.
     */
    private function readDeclaredCount(string $payload, int $countStart, int $countEnd): int
    {
        $literal = substr($payload, $countStart, $countEnd - $countStart);
        $declaredCount = $this->readUnsignedInteger($payload, $countStart, $countEnd);

        if ($declaredCount === null) {
            throw InvalidSerializedDataException::malformedElementCount($payload, $countStart, $literal);
        }

        $remainingBytes = strlen($payload) - ($countEnd + 2);

        if ($declaredCount > $remainingBytes) {
            throw InvalidSerializedDataException::impossibleElementCount(
                $payload,
                $countStart,
                $literal,
                $remainingBytes,
            );
        }

        return $declaredCount;
    }

    /**
     * Parses the digits between the two colons of a string token.
     */
    private function readDeclaredLength(string $payload, int $lengthStart, int $lengthEnd): int
    {
        $declaredLength = $this->readUnsignedInteger($payload, $lengthStart, $lengthEnd);

        return $declaredLength ?? throw InvalidSerializedDataException::malformedLength(
            $payload,
            $lengthStart,
            substr($payload, $lengthStart, $lengthEnd - $lengthStart),
        );
    }

    /**
     * Reads a run of digits, or null when the bytes are not a non-negative integer.
     */
    private function readUnsignedInteger(string $payload, int $start, int $end): ?int
    {
        $literal = substr($payload, $start, $end - $start);

        return preg_match('/^\d+$/', $literal) === 1 ? (int) $literal : null;
    }

    /**
     * Blames the declared length when the real terminator can be found, the missing
     * quote otherwise, so the caller is pointed at the byte that is actually wrong.
     */
    private function rejectLengthOrQuote(
        string $payload,
        int $lengthStart,
        int $valueStart,
        int $declaredLength,
        int $closingQuote,
    ): never {
        $terminator = $this->findSequence($payload, '";', $valueStart);

        if ($terminator === null) {
            $this->expectByte($payload, $closingQuote, '"');
        }

        throw InvalidSerializedDataException::lengthMismatch(
            $payload,
            $lengthStart,
            $declaredLength,
            $terminator - $valueStart,
        );
    }

    /**
     * Counts the value bytes actually present, ignoring a trailing `";` if there is one.
     *
     * Reads only the bytes left for the value: they run to the end of the payload, because
     * this is asked only once the declared length has been found to overrun it.
     */
    private function actualLengthOf(string $payload, int $valueStart): int
    {
        $remaining = substr($payload, $valueStart);

        return str_ends_with($remaining, '";')
            ? strlen($remaining) - 2
            : strlen($remaining);
    }

    /**
     * Finds the next occurrence of a byte sequence, or null when there is none.
     *
     * Guards the search offset itself: strpos() raises a ValueError when asked to
     * start past the end of the string, which a truncated payload does routinely.
     */
    private function findSequence(string $payload, string $sequence, int $from): ?int
    {
        if ($from >= strlen($payload)) {
            return null;
        }

        $found = strpos($payload, $sequence, $from);

        return $found === false ? null : $found;
    }

    /**
     * Asserts that a specific byte sits at the given offset.
     */
    private function expectByte(string $payload, int $offset, string $expected): void
    {
        if ($offset >= strlen($payload)) {
            throw InvalidSerializedDataException::truncatedPayload($payload, $expected);
        }

        if ($payload[$offset] !== $expected) {
            throw InvalidSerializedDataException::unexpectedByte($payload, $offset, $expected);
        }
    }

    /**
     * Tells whether a literal is a serialized boolean.
     */
    private function isValidBoolean(string $literal): bool
    {
        return $literal === '0' || $literal === '1';
    }

    /**
     * Tells whether a literal is a serialized integer.
     */
    private function isValidInteger(string $literal): bool
    {
        return preg_match('/^-?\d+$/', $literal) === 1;
    }

    /**
     * Tells whether a literal is a serialized float, including the non-finite forms.
     */
    private function isValidFloat(string $literal): bool
    {
        return preg_match('/^-?(?:\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|INF|NAN)$/', $literal) === 1;
    }
}
