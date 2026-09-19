<?php

declare(strict_types=1);

use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Serialized;

/**
 * Wraps property names and values into an object payload of the given class.
 */
function objectWith(string ...$parts): string
{
    $body = '';

    foreach (array_chunk($parts, 2) as [$name, $value]) {
        $body .= 's:'.strlen($name).':"'.$name.'";'.$value;
    }

    return 'O:8:"stdClass":'.(count($parts) / 2).':{'.$body.'}';
}

/**
 * Converts with stdClass allowed, the only way to reach a property name at all.
 */
function convertObject(string $payload): string
{
    return Serialized::make()->allowClasses([stdClass::class])->compact()->toJson($payload);
}

it('rejects a property name that does not survive demangling', function (string $name) {
    expect(fn () => convertObject(objectWith($name, 'i:1;')))
        ->toThrow(UnrepresentableValueException::class);
})->with([
    'a bare NUL' => "\x00",
    'a NUL then text' => "\x00ab",
    'a name that demangles to one holding a NUL' => "\x00A\x00b\x00c",
    'a trailing NUL' => "ab\x00",
]);

it('points at the property name rather than throwing an Error', function () {
    $payload = objectWith("\x00", 'd:1.5;');
    $diagnostic = diagnosticFor(fn () => convertObject($payload));

    expect($diagnostic->offset)->toBe(strpos($payload, 's:1:"') + 5)
        ->and($diagnostic->reason)->toContain('property name');
});

it('still converts the mangled names PHP really writes', function () {
    $json = convertObject(objectWith("\x00stdClass\x00secret", 'i:1;', "\x00*\x00guarded", 'i:2;'));

    expect($json)->toBe('{"secret":1,"guarded":2}');
});

it('leaves a NUL alone where it is data rather than a name', function (string $payload, string $json) {
    expect(Serialized::make()->compact()->toJson($payload))->toBe($json);
})->with([
    'NUL inside a string value' => ['s:3:"a'."\x00".'b";', '"a\u0000b"'],
    'NUL as an array key' => ['a:1:{s:1:"'."\x00".'";i:1;}', '{"\u0000":1}'],
]);
