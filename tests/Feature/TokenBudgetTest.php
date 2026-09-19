<?php

declare(strict_types=1);

use Serialized\Exceptions\LimitExceededException;
use Serialized\Serialized;
use Tests\Support\LegacyBox;

/**
 * Builds a flat array of the given number of null values, the cheapest token there is.
 */
function flatArray(int $values): string
{
    $pairs = '';

    for ($key = 0; $key < $values; $key++) {
        $pairs .= 'i:'.$key.';N;';
    }

    return 'a:'.$values.':{'.$pairs.'}';
}

it('stops lexing once the element budget is spent', function () {
    expect(fn () => Serialized::make()->withMaxElements(100)->toJson(flatArray(50_000)))
        ->toThrow(LimitExceededException::class);
});

it('reports the ceiling it stopped at rather than a total it never counted', function () {
    $diagnostic = diagnosticFor(
        fn () => Serialized::make()->withMaxElements(100)->toJson(flatArray(50_000)),
    );

    expect($diagnostic->reason)->toContain('more than 100')
        ->and($diagnostic->reason)->not->toContain('100000');
});

it('does not allocate the whole token stream before refusing it', function () {
    $payload = flatArray(200_000);

    gc_collect_cycles();
    memory_reset_peak_usage();
    $before = memory_get_peak_usage();

    try {
        Serialized::make()->withMaxElements(1_000)->toJson($payload);
    } catch (LimitExceededException) {
        // The refusal is the point; what it cost getting there is what is under test.
        // Peak, not current: tokens are freed on the way out, so only the peak shows
        // whether the whole stream was ever built.
    }

    $peaked = memory_get_peak_usage() - $before;

    expect($peaked)->toBeLessThan(4 * 1024 * 1024);
});

it('spends one element budget across a custom-serialized body and the payload holding it', function () {
    $body = flatArray(200);
    $payload = 'C:'.strlen(LegacyBox::class).':"'.LegacyBox::class.'":'.strlen($body).':{'.$body.'}';

    expect(fn () => Serialized::make()->allowClasses([LegacyBox::class])->withMaxElements(150)->toJson($payload))
        ->toThrow(LimitExceededException::class);
});

it('still converts a payload inside its element budget', function () {
    expect(Serialized::make()->withMaxElements(10)->compact()->toJson(flatArray(2)))
        ->toBe('[null,null]');
});
