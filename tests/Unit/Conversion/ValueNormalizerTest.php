<?php

declare(strict_types=1);

use Serialized\Conversion\ValueNormalizer;
use Tests\Support\Money;
use Tests\Support\Suit;

it('returns a scalar untouched', function (mixed $value) {
    expect(new ValueNormalizer()->normalize($value))->toBe($value);
})->with([null, true, 42, 1.5, 'Chrome']);

it('turns an object into a stdClass carrying every property', function () {
    $normalized = new ValueNormalizer()->normalize(new Money(5, 'USD'));

    expect($normalized)->toBeInstanceOf(stdClass::class)
        ->and((array) $normalized)->toBe(['amount' => 5, 'currency' => 'USD']);
});

it('walks into arrays, keeping a list a list', function () {
    $normalized = new ValueNormalizer()->normalize([new Money(1, 'USD'), 'plain']);

    $money = new stdClass;
    $money->amount = 1;
    $money->currency = 'USD';

    expect($normalized)->toEqual([$money, 'plain']);
});

it('leaves an enum for json_encode to render', function () {
    expect(new ValueNormalizer()->normalize(Suit::Hearts))->toBe(Suit::Hearts);
});

it('keeps an array key that looks numeric', function () {
    $normalized = new ValueNormalizer()->normalize(['0' => 'zero']);

    expect($normalized)->toBe([0 => 'zero']);
});
