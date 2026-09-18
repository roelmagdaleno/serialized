<?php

declare(strict_types=1);

use Serialized\Serialized;

it('converts a real payload to JSON that matches PHP itself', function (string $fixture) {
    $payload = storedPayload($fixture);

    expect(Serialized::toJson($payload))
        ->toBe(json_encode(unserialize($payload), Serialized::make()->options()->jsonFlags));
})->with(['wordpress-attachment-metadata', 'laravel-session', 'deeply-nested']);

it('unserializes a real payload to the same value PHP does', function (string $fixture) {
    $payload = storedPayload($fixture);

    expect(Serialized::toArray($payload))->toBe(unserialize($payload));
})->with(['wordpress-attachment-metadata', 'laravel-session', 'deeply-nested']);

it('accepts a real payload as valid', function (string $fixture) {
    expect(Serialized::isValid(storedPayload($fixture)))->toBeTrue();
})->with(['wordpress-attachment-metadata', 'laravel-session', 'deeply-nested']);

it('rejects a real payload whose first declared length is corrupted', function (string $fixture) {
    $payload = storedPayload($fixture);
    $payload[(int) strpos($payload, 's:') + 2] = 'X';

    expect(Serialized::isValid($payload))->toBeFalse()
        ->and(phpRejects($payload))->toBeTrue();
})->with(['wordpress-attachment-metadata', 'laravel-session', 'deeply-nested']);

it('rejects a real payload whose closing brace is removed', function (string $fixture) {
    $payload = substr(storedPayload($fixture), 0, -1);

    expect(Serialized::isValid($payload))->toBeFalse()
        ->and(phpRejects($payload))->toBeTrue();
})->with(['wordpress-attachment-metadata', 'laravel-session', 'deeply-nested']);

it('agrees with PHP about every value serialize() can produce', function (mixed $value) {
    $payload = serialize($value);

    expect(Serialized::toArray($payload))->toBe(unserialize($payload))
        ->and(Serialized::isValid($payload))->toBeTrue();
})->with([
    'empty array' => [[]],
    'empty string' => [''],
    'zero' => [0],
    'negative zero float' => [-0.0],
    'max int' => [PHP_INT_MAX],
    'min int' => [PHP_INT_MIN],
    'small float' => [PHP_FLOAT_MIN],
    'large float' => [PHP_FLOAT_MAX],
    'float epsilon' => [PHP_FLOAT_EPSILON],
    'true' => [true],
    'false' => [false],
    'null' => [null],
    'string with quotes' => ['he said "hi";'],
    'string with braces' => ['a:1:{i:0;N;}'],
    'string with newlines' => ["line\nline\r\n"],
    'string with nul is rejected' => ["has\0nul"],
    'nested empty arrays' => [[[], [[]], [[[]]]]],
    'numeric string keys' => [['1' => 'a', '02' => 'b']],
    'long list' => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
]);
