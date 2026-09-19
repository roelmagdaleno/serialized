<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

/**
 * One lexed piece of a serialized payload, with the byte range it came from.
 *
 * A token keeps offsets into the payload rather than copies of it. A payload at the byte
 * limit is millions of tokens, and a copy of each token's bytes and of its literal was
 * most of what one cost — while the payload they were cut from is already in memory, and
 * every token already knows where in it to look.
 */
final readonly class Token
{
    /**
     * @param  string  $payload  the payload this token was lexed from, shared by every token
     * @param  int  $offset  byte position of the token's first byte in the payload
     * @param  int  $length  how many bytes the token occupies
     * @param  int|null  $literalOffset  byte position where the token's value starts
     * @param  int|null  $literalLength  how many bytes that value occupies
     * @param  int|null  $declaredCount  the number of key/value pairs an array token declares
     * @param  string|null  $className  the class an object token names
     */
    public function __construct(
        public TokenType $type,
        public string $payload,
        public int $offset,
        public int $length,
        public ?int $literalOffset = null,
        public ?int $literalLength = null,
        public ?int $declaredCount = null,
        public ?string $className = null,
    ) {}

    /**
     * Returns the byte position just past this token, where the next one begins.
     */
    public function endOffset(): int
    {
        return $this->offset + $this->length;
    }

    /**
     * Returns the exact bytes the token was lexed from.
     */
    public function raw(): string
    {
        return substr($this->payload, $this->offset, $this->length);
    }

    /**
     * Returns the token's value as written, empty for a token that has none.
     */
    public function literal(): string
    {
        if ($this->literalOffset === null || $this->literalLength === null) {
            return '';
        }

        return substr($this->payload, $this->literalOffset, $this->literalLength);
    }
}
