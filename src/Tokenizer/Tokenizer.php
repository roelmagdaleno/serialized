<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

use Serialized\Exceptions\InvalidSerializedDataException;

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
     * Lexes the whole payload, left to right.
     *
     * @return list<Token>
     *
     * @throws InvalidSerializedDataException when any token is malformed
     */
    public function tokenize(string $payload): array
    {
        if ($payload === '') {
            throw InvalidSerializedDataException::emptyPayload();
        }

        $tokens = [];
        $cursor = 0;
        $payloadLength = strlen($payload);

        while ($cursor < $payloadLength) {
            $token = $this->readToken($payload, $cursor);
            $tokens[] = $token;
            $cursor = $token->endOffset();
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
            'a' => $this->readArrayHeader($payload, $offset),
            'O' => $this->readObjectHeader($payload, $offset),
            'C' => $this->readCustomObject($payload, $offset),
            'R' => $this->readScalar($payload, $offset, TokenType::Reference, $this->isValidInteger(...)),
            'r' => $this->readScalar($payload, $offset, TokenType::ValueReference, $this->isValidInteger(...)),
            'E' => $this->readEnum($payload, $offset),
            '}' => new Token(TokenType::Close, $offset, '}'),
            default => throw InvalidSerializedDataException::unknownTypePrefix($payload, $offset),
        };
    }

    /**
     * Reads `N;`, the only token with no value of its own.
     */
    private function readNull(string $payload, int $offset): Token
    {
        $this->expectByte($payload, $offset + 1, ';');

        return new Token(TokenType::Null, $offset, 'N;');
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
            $offset,
            substr($payload, $offset, $terminator + 1 - $offset),
            $literal,
            literalOffset: $literalStart,
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
            $offset,
            substr($payload, $offset, $closingQuote + 2 - $offset),
            substr($payload, $valueStart, $declaredLength),
            $declaredLength,
            literalOffset: $valueStart,
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

        $declaredCount = $this->readUnsignedInteger($payload, $countStart, $countEnd);

        if ($declaredCount === null) {
            throw InvalidSerializedDataException::malformedElementCount(
                $payload,
                $countStart,
                substr($payload, $countStart, $countEnd - $countStart),
            );
        }

        $this->expectByte($payload, $countEnd + 1, '{');

        return new Token(
            TokenType::Array,
            $offset,
            substr($payload, $offset, $countEnd + 2 - $offset),
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

        $declaredCount = $this->readUnsignedInteger($payload, $countStart, $countEnd);

        if ($declaredCount === null) {
            throw InvalidSerializedDataException::malformedElementCount(
                $payload,
                $countStart,
                substr($payload, $countStart, $countEnd - $countStart),
            );
        }

        $this->expectByte($payload, $countEnd + 1, '{');

        return new Token(
            TokenType::Object,
            $offset,
            substr($payload, $offset, $countEnd + 2 - $offset),
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
            $offset,
            substr($payload, $offset, $bodyStart + $declaredLength + 1 - $offset),
            substr($payload, $bodyStart, $declaredLength),
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
            $offset,
            substr($payload, $offset, $afterCaseName + 1 - $offset),
            $caseName,
            className: $separator === false ? $caseName : substr($caseName, 0, $separator),
            literalOffset: $afterCaseName - 1 - strlen($caseName),
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
