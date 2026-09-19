<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A class that has lost a property its stored payloads still carry.
 *
 * Restoring one of those payloads makes PHP raise a deprecation for the dynamic property,
 * which is a remark about the class, not a statement that the payload is unreadable.
 */
final class DriftedClass
{
    public function __construct(public int $amount = 0) {}
}
