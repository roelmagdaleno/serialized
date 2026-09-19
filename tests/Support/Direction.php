<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A non-backed enum, which has no JSON representation.
 */
enum Direction
{
    case North;
}
