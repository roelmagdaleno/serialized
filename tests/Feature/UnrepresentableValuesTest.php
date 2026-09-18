<?php

declare(strict_types=1);

use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Serialized;

it('rejects a string holding bytes that are not valid UTF-8', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson("s:1:\"\xff\";"));

    expect($diagnostic->offset)->toBe(5)
        ->and($diagnostic->reason)->toContain('UTF-8')
        ->and($diagnostic->fix)->toContain('Base64');
});

it('rejects a non-finite float', function (string $payload, string $literal) {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson($payload));

    expect($diagnostic->offset)->toBe(2)
        ->and($diagnostic->reason)->toContain($literal)
        ->and($diagnostic->fix)->not->toBeEmpty();
})->with([
    'nan' => ['d:NAN;', 'NAN'],
    'infinity' => ['d:INF;', 'INF'],
    'negative infinity' => ['d:-INF;', '-INF'],
]);

it('rejects an unrepresentable value nested inside an array', function () {
    expect(fn () => Serialized::toJson('a:1:{i:0;d:NAN;}'))
        ->toThrow(UnrepresentableValueException::class);

    expect(diagnosticFor(fn () => Serialized::toJson("a:1:{i:0;s:1:\"\xff\";}"))->offset)->toBe(14);
});

it('keeps valid multibyte text unescaped', function (string $text) {
    expect(Serialized::toJson(serialize($text)))->toBe('"'.$text.'"');
})->with([
    'accents' => ['café'],
    'emoji' => ['🎉'],
    'cjk' => ['日本語'],
    'mixed' => ['Ünïcodé 🎉 日本'],
]);

it('accepts finite floats at the edges of the range', function (string $payload) {
    expect(Serialized::isValid($payload))->toBeTrue();
})->with(['d:0;', 'd:-0;', 'd:1.0E+25;', 'd:1.7976931348623157E+308;']);

it('reports an unrepresentable payload as invalid rather than throwing', function () {
    expect(Serialized::isValid('d:NAN;'))->toBeFalse()
        ->and(Serialized::tryToJson('d:NAN;'))->toBeNull();
});

it('never lets json_encode be the thing that rejects a value', function (string $payload) {
    expect(fn () => Serialized::toJson($payload))->toThrow(UnrepresentableValueException::class);
})->with([
    'bare invalid byte' => ["s:1:\"\xff\";"],
    'invalid byte among valid text' => ["s:5:\"a\xffb\xfec\";"],
    'truncated utf8 sequence' => ["s:2:\"\xc3\x28\";"],
    'nan' => ['d:NAN;'],
]);
