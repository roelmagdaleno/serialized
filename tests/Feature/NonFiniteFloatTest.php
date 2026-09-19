<?php

declare(strict_types=1);

use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Serialized;

it('rejects a float whose value is not finite', function (string $payload) {
    expect(fn () => Serialized::toJson($payload))->toThrow(UnrepresentableValueException::class);
})->with([
    'spelled NAN' => 'd:NAN;',
    'spelled INF' => 'd:INF;',
    'spelled -INF' => 'd:-INF;',
    'overflowing the double range' => 'd:1e999;',
    'overflowing negatively' => 'd:-1e999;',
    'just past the maximum exponent' => 'd:1e309;',
    'nested in an array' => 'a:1:{i:0;d:1e999;}',
]);

it('points at the overflowing literal rather than reaching the encoder', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{i:0;d:1e999;}'));

    expect($diagnostic->offset)->toBe(strpos('a:1:{i:0;d:1e999;}', '1e999'))
        ->and($diagnostic->reason)->toContain('1e999');
});

it('still converts a float at the edge of the range', function (string $payload, string $json) {
    expect(Serialized::make()->compact()->toJson($payload))->toBe($json);
})->with([
    'largest finite double' => ['d:1.7976931348623157E+308;', '1.7976931348623157e+308'],
    'small exponent' => ['d:1e308;', '1.0e+308'],
    'ordinary float' => ['d:1.5;', '1.5'],
    'zero' => ['d:0;', '0'],
]);
