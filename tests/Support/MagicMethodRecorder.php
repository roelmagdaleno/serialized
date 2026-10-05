<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Records every magic method PHP runs while restoring or discarding it.
 *
 * Stands in for the gadget class a hostile payload names: a test proves a payload was
 * converted without it by asserting nothing was recorded.
 */
final class MagicMethodRecorder
{
    /**
     * @var list<string>
     */
    public static array $invoked = [];

    public string $command = 'rm -rf /';

    /**
     * Records that PHP woke the object.
     */
    public function __wakeup(): void
    {
        self::$invoked[] = '__wakeup';
    }

    /**
     * Records that PHP destroyed the object.
     */
    public function __destruct()
    {
        self::$invoked[] = '__destruct';
    }
}
