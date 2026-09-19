<?php

declare(strict_types=1);

namespace Serialized\Parser;

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
     * Opens a frame for a structure that declares the given number of pairs.
     */
    public function __construct(
        public readonly int $offset,
        public readonly int $declaredCount,
    ) {
        $this->slotsLeft = $declaredCount * 2;
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
        return intdiv($this->declaredCount * 2 - $this->slotsLeft, 2);
    }
}
