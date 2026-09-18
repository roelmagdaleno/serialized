<?php

declare(strict_types=1);

use Serialized\Conversion\JsonEncoder;
use Serialized\Exceptions\JsonEncodingException;
use Serialized\Options;

it('pretty prints with four spaces by default', function () {
    $json = new JsonEncoder()->encode(['name' => 'Chrome', 'mobile' => false], new Options);

    expect($json)->toBe(<<<'JSON'
    {
        "name": "Chrome",
        "mobile": false
    }
    JSON);
});

it('leaves slashes and unicode unescaped', function () {
    $json = new JsonEncoder()->encode(['url' => 'https://s.w.org/x?1', 'name' => 'café'], new Options);

    expect($json)->toContain('https://s.w.org/x?1')
        ->and($json)->toContain('café');
});

it('encodes a list as a JSON array and a map as a JSON object', function () {
    expect(new JsonEncoder()->encode([1, 2], new Options))->toStartWith('[')
        ->and(new JsonEncoder()->encode(['a' => 1], new Options))->toStartWith('{');
});

it('wraps an encoding failure as a package exception', function () {
    expect(fn () => new JsonEncoder()->encode("\xff", new Options))
        ->toThrow(JsonEncodingException::class);
});
