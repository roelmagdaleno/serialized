<?php

declare(strict_types=1);

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Options;
use Serialized\Serialized;
use Serialized\SerializedConverter;

it('converts every scalar payload to JSON', function (string $payload, string $json) {
    expect(Serialized::toJson($payload))->toBe($json);
})->with([
    'null' => ['N;', 'null'],
    'false' => ['b:0;', 'false'],
    'true' => ['b:1;', 'true'],
    'integer' => ['i:42;', '42'],
    'float' => ['d:1.5;', '1.5'],
    'string' => ['s:6:"Chrome";', '"Chrome"'],
]);

it('returns the unserialized PHP value from toArray', function () {
    expect(Serialized::toArray('s:6:"Chrome";'))->toBe('Chrome')
        ->and(Serialized::toArray('i:42;'))->toBe(42);
});

it('validates without converting', function () {
    expect(Serialized::isValid('i:42;'))->toBeTrue()
        ->and(Serialized::isValid('i:4x2;'))->toBeFalse()
        ->and(Serialized::isValid(''))->toBeFalse();
});

it('never throws from isValid', function () {
    expect(Serialized::isValid("\xff\xfe nonsense"))->toBeFalse();
});

it('returns null instead of throwing from tryToJson', function () {
    expect(Serialized::tryToJson('i:4x2;'))->toBeNull()
        ->and(Serialized::tryToJson('i:42;'))->toBe('42');
});

it('throws a diagnostic-carrying exception from toJson', function () {
    expect(fn () => Serialized::toJson('i:4x2;'))->toThrow(InvalidSerializedDataException::class);

    expect(diagnosticFor(fn () => Serialized::toJson('i:4x2;'))->offset)->toBe(2);
});

it('exposes the same verbs on the converter as on the static entry point', function () {
    $converter = Serialized::make();

    expect($converter)->toBeInstanceOf(SerializedConverter::class)
        ->and($converter->toJson('i:42;'))->toBe('42')
        ->and($converter->tryToJson('i:42;'))->toBe('42')
        ->and($converter->toArray('i:42;'))->toBe(42)
        ->and($converter->isValid('i:42;'))->toBeTrue();
});

it('leaves the original converter unchanged when reconfigured', function () {
    $original = Serialized::make();
    $configured = $original->withMaxDepth(8);

    expect($configured)->not->toBe($original)
        ->and($configured->options()->maxDepth)->toBe(8)
        ->and($original->options()->maxDepth)->toBe(64);
});

it('carries every unchanged option across a reconfiguration', function () {
    $converter = Serialized::make()->withMaxBytes(1024);

    expect($converter->options()->maxBytes)->toBe(1024)
        ->and($converter->options()->maxDepth)->toBe(64)
        ->and($converter->options()->maxElements)->toBe(1_000_000)
        ->and($converter->options()->allowedClasses)->toBe([])
        ->and($converter->options()->jsonFlags)->toBe(new Options()->jsonFlags);
});
