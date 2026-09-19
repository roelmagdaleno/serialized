<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * An abstract class: loadable, but PHP cannot build a value of it.
 */
abstract class AbstractLedger
{
    public int $entries = 0;
}
