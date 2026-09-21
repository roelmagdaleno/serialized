<?php

declare(strict_types=1);

use Serialized\Serialized;

/**
 * Warnings PHP raises while still handing back the value it read.
 *
 * `unserialize()` reports an out-of-range integer at warning level and returns
 * PHP_INT_MAX, so the payload decoded. Reading the warning as a refusal would reject a
 * value PHP just produced.
 */
it('converts an integer too large for the platform', function () {
    expect(Serialized::toJson('i:99999999999999999999999;'))->toBe((string) PHP_INT_MAX)
        ->and(Serialized::toArray('i:99999999999999999999999;'))->toBe(PHP_INT_MAX);
});

it('converts a negative integer too large for the platform', function () {
    expect(Serialized::toArray('i:-99999999999999999999999;'))->toBe(PHP_INT_MIN);
});

it('still reports a payload PHP genuinely refuses', function () {
    expect(Serialized::isValid('i:99999999999999999999999;'))->toBeTrue();
});
