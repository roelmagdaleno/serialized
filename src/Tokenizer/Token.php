<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

/**
 * One lexed piece of a serialized payload, with the byte range it came from.
 */
final readonly class Token
{
    /**
     * @param  int  $offset  byte position of the token's first byte in the payload
     * @param  string  $raw  the exact bytes the token was lexed from
     * @param  string  $literal  the token's value as written, empty for null
     * @param  int|null  $declaredLength  the byte length a string token declares
     * @param  int|null  $declaredCount  the number of key/value pairs an array token declares
     * @param  string|null  $className  the class an object token names
     * @param  int|null  $literalOffset  byte position where the literal itself starts
     */
    public function __construct(
        public TokenType $type,
        public int $offset,
        public string $raw,
        public string $literal = '',
        public ?int $declaredLength = null,
        public ?int $declaredCount = null,
        public ?string $className = null,
        public ?int $literalOffset = null,
    ) {}

    /**
     * Returns the byte position just past this token, where the next one begins.
     */
    public function endOffset(): int
    {
        return $this->offset + strlen($this->raw);
    }
}
