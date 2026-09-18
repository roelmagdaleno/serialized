<?php

declare(strict_types=1);

use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Serialized;

it('rejects an object by default', function () {
    expect(fn () => Serialized::toJson('O:8:"stdClass":0:{}'))
        ->toThrow(UnsafeSerializedDataException::class);
});

it('names the class, its offset and the way to allow it', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('O:8:"stdClass":0:{}'));

    expect($diagnostic->offset)->toBe(0)
        ->and($diagnostic->reason)->toContain('stdClass')
        ->and($diagnostic->reason)->toContain('__wakeup')
        ->and($diagnostic->fix)->toContain('allowClasses');
});

it('converts an object once its class is allowed', function () {
    $json = Serialized::make()->allowClasses([stdClass::class])->toJson('O:8:"stdClass":0:{}');

    expect($json)->toBe('{}');
});

it('converts an allowed object with properties', function () {
    $json = Serialized::make()
        ->allowClasses([stdClass::class])
        ->toJson('O:8:"stdClass":1:{s:4:"name";s:6:"Chrome";}');

    expect($json)->toBe(<<<'JSON'
    {
        "name": "Chrome"
    }
    JSON);
});

it('rejects a disallowed class nested inside an allowed object', function () {
    $payload = 'O:8:"stdClass":1:{s:4:"when";O:8:"DateTime":0:{}}';

    $diagnostic = diagnosticFor(
        fn () => Serialized::make()->allowClasses([stdClass::class])->toJson($payload),
    );

    expect($diagnostic->reason)->toContain('DateTime')
        ->and($diagnostic->offset)->toBe(29);
});

it('rejects a disallowed class nested inside an array', function () {
    expect(fn () => Serialized::toJson('a:1:{i:0;O:8:"stdClass":0:{}}'))
        ->toThrow(UnsafeSerializedDataException::class);
});

it('rejects a custom-serialized object by default', function () {
    expect(fn () => Serialized::toJson('C:8:"stdClass":4:{data}'))
        ->toThrow(UnsafeSerializedDataException::class);
});

it('rejects an enum by default', function () {
    expect(fn () => Serialized::toJson('E:11:"Suit:Hearts";'))
        ->toThrow(UnsafeSerializedDataException::class);
});

it('rejects both reference forms', function (string $payload) {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson($payload));

    expect($diagnostic->reason)->toContain('reference')
        ->and($diagnostic->fix)->not->toBeEmpty();
})->with([
    'back reference' => ['a:2:{i:0;N;i:1;R:2;}'],
    'value reference' => ['a:2:{i:0;s:1:"a";i:1;r:1;}'],
]);

it('never produces an incomplete class for any input', function (string $payload) {
    $value = Serialized::tryToJson($payload);

    expect($value)->toBeNull();
})->with([
    'plain object' => ['O:8:"stdClass":0:{}'],
    'unknown class' => ['O:20:"App\Models\NoSuchXY":0:{}'],
    'nested object' => ['a:1:{i:0;O:8:"stdClass":0:{}}'],
    'custom object' => ['C:8:"stdClass":4:{data}'],
]);

it('reports an object payload as invalid', function () {
    expect(Serialized::isValid('O:8:"stdClass":0:{}'))->toBeFalse()
        ->and(Serialized::make()->allowClasses([stdClass::class])->isValid('O:8:"stdClass":0:{}'))->toBeTrue();
});
