<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A value object whose data lives entirely in non-public properties.
 */
final class Money
{
    public function __construct(
        private int $amount = 5,
        protected string $currency = 'USD',
    ) {}

    /**
     * Reads the properties back, so a test can prove toArray() returned the real object.
     */
    public function describe(): string
    {
        return "{$this->amount} {$this->currency}";
    }
}
