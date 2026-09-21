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

/**
 * A reference names a value the same payload already carries, so resolving one can
 * only ever duplicate data the caller already has. It builds nothing and runs
 * nothing, which is why it is not refused the way a class is.
 */
it('resolves a back reference without instantiating anything', function () {
    expect(Serialized::make()->compact()->toJson('a:2:{i:0;N;i:1;R:2;}'))->toBe('[null,null]');
});

it('refuses a reference that makes the value contain itself', function () {
    $loop = [];
    $loop['self'] = &$loop;

    $diagnostic = diagnosticFor(fn () => Serialized::toJson(serialize($loop)));

    expect($diagnostic->reason)->toContain('never ends')
        ->and($diagnostic->fix)->not->toBeEmpty();
});

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

// A config-driven allow-list hands over whatever spelling was written down, so every
// spelling PHP itself accepts has to reach unserialize() as the same class.
it('honours an allowed class however the caller spells it', function (string $spelling) {
    /** @var class-string $spelling */
    $json = Serialized::make()
        ->allowClasses([$spelling])
        ->compact()
        ->toJson('O:8:"stdClass":1:{s:4:"name";s:6:"Chrome";}');

    expect($json)->toBe('{"name":"Chrome"}');
})->with(['stdClass', '\stdClass', 'STDCLASS', '\STDCLASS']);

it('never lets the incomplete-class pseudo-property reach the output', function () {
    $json = Serialized::make()
        ->allowClasses(['\stdClass'])
        ->compact()
        ->toJson('O:8:"stdClass":1:{s:2:"id";i:1;}');

    expect($json)->not->toContain('__PHP_Incomplete_Class_Name');
});
