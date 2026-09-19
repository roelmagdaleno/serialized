<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A backed enum, which JSON can carry as its value.
 */
enum Suit: string
{
    case Hearts = 'H';
}
