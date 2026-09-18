<?php

declare(strict_types=1);

use Serialized\Conversion\SafeUnserializer;
use Serialized\Exceptions\InvalidSerializedDataException;

it('unserializes every scalar type', function (string $payload, mixed $expected) {
    expect(new SafeUnserializer()->unserialize($payload, []))->toBe($expected);
})->with([
    'null' => ['N;', null],
    'false' => ['b:0;', false],
    'true' => ['b:1;', true],
    'integer' => ['i:42;', 42],
    'float' => ['d:1.5;', 1.5],
    'string' => ['s:6:"Chrome";', 'Chrome'],
]);

it('never instantiates a class that was not allowed', function () {
    $value = new SafeUnserializer()->unserialize('a:1:{i:0;i:1;}', []);

    expect($value)->toBe([0 => 1]);
});

it('instantiates only the classes it was given', function () {
    $value = new SafeUnserializer()->unserialize('O:8:"stdClass":0:{}', [stdClass::class]);

    expect($value)->toBeInstanceOf(stdClass::class);
});

it('turns the warning PHP raises into a typed exception', function () {
    $diagnostic = diagnosticFor(fn () => new SafeUnserializer()->unserialize('i:1', []));

    expect($diagnostic->offset)->toBeGreaterThanOrEqual(0)
        ->and($diagnostic->reason)->not->toBeEmpty();
});

it('lets no PHP warning escape to the caller', function () {
    $escaped = [];
    set_error_handler(static function (int $level, string $message) use (&$escaped): bool {
        $escaped[] = $message;

        return true;
    });

    try {
        expect(fn () => new SafeUnserializer()->unserialize('i:1', []))
            ->toThrow(InvalidSerializedDataException::class);
    } finally {
        restore_error_handler();
    }

    expect($escaped)->toBe([]);
});

it('restores the previous error handler even when it throws', function () {
    $handler = static fn (): bool => true;
    set_error_handler($handler);

    try {
        expect(fn () => new SafeUnserializer()->unserialize('i:1', []))
            ->toThrow(InvalidSerializedDataException::class);

        expect(set_error_handler(null))->toBe($handler);
    } finally {
        restore_error_handler();
        restore_error_handler();
    }
});

it('distinguishes a serialized false from a failure', function () {
    expect(new SafeUnserializer()->unserialize('b:0;', []))->toBeFalse();
});
