<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Redeclares the parent's private property, so the serialized payload carries both.
 */
final class SavingsAccount extends Account
{
    private int $balance = 2;

    public int $rate = 3;

    /**
     * Reads the subclass's own private property, the one that shadows the parent's.
     */
    public function ownBalance(): int
    {
        return $this->balance;
    }
}
