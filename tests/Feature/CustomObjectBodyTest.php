<?php

declare(strict_types=1);

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Exceptions\LimitExceededException;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Serialized;
use Tests\Support\LegacyBox;

/**
 * Wraps a body in the C: header that declares it, so a test reads as the body it is about.
 */
function customObject(string $body, string $className = LegacyBox::class): string
{
    return sprintf('C:%d:"%s":%d:{%s}', strlen($className), $className, strlen($body), $body);
}

/**
 * Converts a payload with the legacy box allowed, which is the only way to reach a C: body.
 */
function convertCustomObject(string $payload): string
{
    return Serialized::make()->allowClasses([LegacyBox::class])->compact()->toJson($payload);
}

it('converts a well-formed custom-serialized body', function () {
    expect(convertCustomObject(customObject('a:1:{s:1:"a";i:1;}')))->toBe('{"contents":{"a":1}}');
});

it('rejects a reference hidden in a custom-serialized body', function () {
    $payload = customObject('a:2:{i:0;a:0:{}i:1;R:2;}');

    expect(fn () => convertCustomObject($payload))->toThrow(UnsafeSerializedDataException::class);
});

it('points a body diagnostic at a byte of the whole payload', function () {
    $payload = customObject('a:2:{i:0;a:0:{}i:1;R:2;}');
    $diagnostic = diagnosticFor(fn () => convertCustomObject($payload));

    expect($diagnostic->payload)->toBe($payload)
        ->and($diagnostic->offset)->toBe(strpos($payload, 'R:2;'));
});

it('rejects a class named inside a custom-serialized body but not allowed', function () {
    $payload = customObject('O:8:"DateTime":0:{}');

    expect(fn () => convertCustomObject($payload))->toThrow(UnsafeSerializedDataException::class);
});

it('rejects a non-UTF-8 string inside a custom-serialized body', function () {
    $payload = customObject('s:1:"'."\xff".'";');

    expect(fn () => convertCustomObject($payload))->toThrow(UnrepresentableValueException::class);
});

it('rejects a non-finite float inside a custom-serialized body', function () {
    expect(fn () => convertCustomObject(customObject('d:NAN;')))
        ->toThrow(UnrepresentableValueException::class);
});

it('spends the depth budget on a custom-serialized body', function () {
    $body = 'i:1;';

    for ($level = 0; $level < 40; $level++) {
        $body = 'a:1:{i:0;'.$body.'}';
    }

    expect(fn () => Serialized::make()
        ->allowClasses([LegacyBox::class])
        ->withMaxDepth(20)
        ->toJson(customObject($body)))->toThrow(LimitExceededException::class);
});

it('spends the element budget on a custom-serialized body', function () {
    $body = 'a:3:{i:0;i:0;i:1;i:1;i:2;i:2;}';

    expect(fn () => Serialized::make()
        ->allowClasses([LegacyBox::class])
        ->withMaxElements(4)
        ->toJson(customObject($body)))->toThrow(LimitExceededException::class);
});

it('rejects a custom-serialized body that is not one complete value', function (string $body) {
    expect(fn () => convertCustomObject(customObject($body)))
        ->toThrow(InvalidSerializedDataException::class);
})->with([
    'trailing value' => 'i:1;i:2;',
    'truncated value' => 'a:1:{i:0;',
    'opaque bytes' => 'not serialized at all',
    'empty body' => '',
]);

it('rejects a body whose declared length does not reach its closing brace', function () {
    $payload = 'C:'.strlen(LegacyBox::class).':"'.LegacyBox::class.'":4:{s:40:"aaaa"}';

    expect(fn () => convertCustomObject($payload))->toThrow(InvalidSerializedDataException::class);
});

it('rejects a value inside a body that reaches past the body', function () {
    // The body declares five bytes, but the string it opens borrows its closing quote from
    // an outer string six bytes later: the value PHP reads is not the value we validated.
    $payload = 'a:2:{i:0;C:'.strlen(LegacyBox::class).':"'.LegacyBox::class.'":5:{s:7:"}s:1:"x";i:9;}';
    $diagnostic = diagnosticFor(fn () => convertCustomObject($payload));

    expect($diagnostic->reason)->toContain('runs past the declared end')
        ->and($diagnostic->offset)->toBe(strpos($payload, 's:7:"') + 5);
});
