<?php

declare(strict_types=1);

use Serialized\Exceptions\LimitExceededException;
use Serialized\Serialized;

it('rejects a payload larger than the byte limit', function () {
    $payload = serialize(str_repeat('x', 2_000));

    expect(fn () => Serialized::make()->withMaxBytes(100)->toJson($payload))
        ->toThrow(LimitExceededException::class);
});

it('names the limit, the configured value and the actual value', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::make()->withMaxBytes(10)->toJson(chromePayload()));

    expect($diagnostic->reason)->toContain('10')
        ->and($diagnostic->reason)->toContain((string) strlen(chromePayload()))
        ->and($diagnostic->fix)->toContain('withMaxBytes');
});

it('rejects a payload nested deeper than the depth limit', function () {
    $payload = str_repeat('a:1:{i:0;', 20).'N;'.str_repeat('}', 20);

    expect(fn () => Serialized::make()->withMaxDepth(10)->toJson($payload))
        ->toThrow(LimitExceededException::class);

    expect(diagnosticFor(fn () => Serialized::make()->withMaxDepth(10)->toJson($payload))->fix)
        ->toContain('withMaxDepth');
});

it('rejects a payload with more elements than the element limit', function () {
    $payload = serialize(range(1, 100));

    expect(fn () => Serialized::make()->withMaxElements(10)->toJson($payload))
        ->toThrow(LimitExceededException::class);

    expect(diagnosticFor(fn () => Serialized::make()->withMaxElements(10)->toJson($payload))->fix)
        ->toContain('withMaxElements');
});

it('accepts a payload once the limit is raised', function () {
    $payload = str_repeat('a:1:{i:0;', 20).'N;'.str_repeat('}', 20);

    expect(Serialized::make()->withMaxDepth(10)->isValid($payload))->toBeFalse()
        ->and(Serialized::make()->withMaxDepth(64)->isValid($payload))->toBeTrue()
        ->and(Serialized::make()->withMaxBytes(10)->isValid('i:1;'))->toBeTrue()
        ->and(Serialized::make()->withMaxElements(2)->isValid('a:1:{i:0;N;}'))->toBeFalse()
        ->and(Serialized::make()->withMaxElements(3)->isValid('a:1:{i:0;N;}'))->toBeTrue();
});

it('rejects an oversized payload without tokenizing it', function () {
    $payload = str_repeat('!', 2_000_000);

    $started = hrtime(true);
    expect(Serialized::make()->withMaxBytes(1_000)->isValid($payload))->toBeFalse();

    expect((hrtime(true) - $started) / 1_000_000)->toBeLessThan(50);
});

it('rejects a deeply nested payload without exhausting memory or the stack', function () {
    $payload = str_repeat('a:1:{i:0;', 500).'N;'.str_repeat('}', 500);

    expect(fn () => Serialized::toJson($payload))->toThrow(LimitExceededException::class);
});

it('rejects a very large payload without exhausting memory', function () {
    $payload = 'a:1:{i:0;'.serialize(str_repeat('x', 20_000_000)).'}';

    expect(fn () => Serialized::toJson($payload))->toThrow(LimitExceededException::class);
});
