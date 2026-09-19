<?php

declare(strict_types=1);

namespace Serialized\Parser;

use Serialized\Tokenizer\Token;

/**
 * Tracks one open array or object while the parser walks the token stream.
 *
 * A structure of N pairs occupies 2N slots, filled key, value, key, value, which is how
 * the parser knows whether the next token is being used as a key. An object's properties
 * are counted the same way: a property name fills a key slot, its value the next.
 */
final class StructureFrame
{
    private int $slotsLeft;

    /**
     * Opens a frame for the structure the given header token declares.
     *
     * The token is kept so a diagnostic can name the structure that went wrong and
     * spell the header the payload would need instead.
     */
    public function __construct(public readonly Token $token)
    {
        $this->slotsLeft = $this->declaredCount() * 2;
    }

    /**
     * Returns how many key/value pairs the header declares.
     */
    public function declaredCount(): int
    {
        return $this->token->declaredCount ?? 0;
    }

    /**
     * Tells whether the next token fills a key slot rather than a value slot.
     */
    public function expectsKey(): bool
    {
        return $this->slotsLeft % 2 === 0;
    }

    /**
     * Tells whether every declared slot has been filled.
     */
    public function isFull(): bool
    {
        return $this->slotsLeft === 0;
    }

    /**
     * Marks one slot as filled.
     */
    public function fillSlot(): void
    {
        $this->slotsLeft--;
    }

    /**
     * Returns how many complete key/value pairs have been seen so far.
     */
    public function filledPairs(): int
    {
        return intdiv($this->declaredCount() * 2 - $this->slotsLeft, 2);
    }
}
