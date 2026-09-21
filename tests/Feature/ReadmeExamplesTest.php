<?php

declare(strict_types=1);

use Serialized\Exceptions\SerializedException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Serialized;
use Tests\Support\Money;

it('converts the README opening example', function () {
    expect(Serialized::toJson('a:2:{s:4:"name";s:6:"Chrome";s:6:"mobile";b:0;}'))->toBe(<<<'JSON'
    {
        "name": "Chrome",
        "mobile": false
    }
    JSON);
});

it('produces the README diagnostic exactly as documented', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}'));

    expect($diagnostic->offset)->toBe(18)
        ->and($diagnostic->reason)->toBe('String length mismatch at offset 18: declared 6 bytes, found 5.')
        ->and($diagnostic->fix)->toBe('Change s:6 to s:5, or restore the missing bytes in the value.');
});

it('renders the README message block exactly as documented', function () {
    try {
        Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}');
    } catch (SerializedException $exception) {
        expect($exception->getMessage())->toBe(<<<'TXT'
        String length mismatch at offset 18: declared 6 bytes, found 5.

        a:1:{s:4:"name";s:6:"Chrom";}
                          ^

        Fix: Change s:6 to s:5, or restore the missing bytes in the value.
        TXT);
    }
});

it('runs the README object example', function () {
    expect(fn () => Serialized::toJson('O:8:"stdClass":0:{}'))->toThrow(UnsafeSerializedDataException::class)
        ->and(Serialized::make()->allowClasses([stdClass::class])->toJson('O:8:"stdClass":0:{}'))->toBe('{}');

    expect(diagnosticFor(fn () => Serialized::toJson('O:8:"stdClass":0:{}'))->reason)
        ->toStartWith('Payload contains an object of class "stdClass" at offset 0.');
});

it('runs the README builder example', function () {
    $converter = Serialized::make()
        ->withMaxBytes(1_000_000)
        ->withMaxDepth(32)
        ->withMaxElements(50_000)
        ->allowClasses([stdClass::class])
        ->compact();

    expect($converter->toJson(chromePayload()))->not->toContain("\n")
        ->and($converter->options()->maxBytes)->toBe(1_000_000)
        ->and($converter->options()->maxDepth)->toBe(32)
        ->and($converter->options()->maxElements)->toBe(50_000);
});

it('converts every property of an allowed object, as the README shows', function () {
    $json = Serialized::make()->allowClasses([Money::class])->compact()->toJson(serialize(new Money));

    expect($json)->toBe('{"amount":5,"currency":"USD"}');
});

it('documents the defaults the README lists', function () {
    $options = Serialized::make()->options();

    expect($options->maxBytes)->toBe(16 * 1024 * 1024)
        ->and($options->maxDepth)->toBe(64)
        ->and($options->maxElements)->toBe(1_000_000)
        ->and($options->allowedClasses)->toBe([]);
});

it('converts the README escaped-string example', function () {
    expect(Serialized::toJson('S:5:"\\68ello";'))->toBe('"hello"');
});
