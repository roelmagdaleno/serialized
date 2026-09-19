<?php

declare(strict_types=1);

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Serialized;

it('refuses an element count the payload cannot hold', function (string $payload) {
    expect(fn () => Serialized::toJson($payload))->toThrow(InvalidSerializedDataException::class);
})->with([
    'array, far past the cast boundary' => 'a:99999999999999999999:{}',
    'object, far past the cast boundary' => 'O:8:"stdClass":99999999999999999999:{}',
    'array, at the doubling boundary' => 'a:4611686018427387904:{}',
    'array, at the integer maximum' => 'a:9223372036854775807:{}',
    'nested in an array' => 'a:1:{i:0;a:99999999999999999999:{}}',
    'nested in an object' => 'O:8:"stdClass":1:{s:1:"a";a:99999999999999999999:{}}',
    'more pairs than bytes' => 'a:1000:{i:0;i:0;}',
]);

it('says what the count would need and where it is', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:99999999999999999999:{}'));

    expect($diagnostic->offset)->toBe(2)
        ->and($diagnostic->reason)->toContain('99999999999999999999')
        ->and($diagnostic->fix)->not->toBeEmpty();
});

it('still accepts a count the remaining bytes can hold', function () {
    expect(Serialized::make()->compact()->toJson('a:2:{i:0;N;i:1;N;}'))->toBe('[null,null]');
});
