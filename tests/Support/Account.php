<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Declares a private $balance that its subclass redeclares, forcing a name collision.
 */
class Account
{
    private int $balance = 1;

    /**
     * Reads the parent's own private property.
     */
    public function inheritedBalance(): int
    {
        return $this->balance;
    }
}
