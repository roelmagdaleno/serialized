<?php

declare(strict_types=1);

use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Serialized;

/**
 * `serialize()` writes `R:` or `r:` whenever the value it is given holds a PHP
 * reference, so back-references arrive in ordinary payloads rather than crafted ones.
 *
 * `unserialize()` resolves them before this package sees a value, so the only thing
 * left to decide is what JSON says about the result: the same value twice for a
 * reference that points sideways, and nothing at all for one that points at an
 * ancestor.
 */
it('writes a shared value out at each place it appears', function () {
    $shared = ['a' => 1];

    expect(Serialized::make()->compact()->toJson(serialize(['first' => &$shared, 'second' => &$shared])))
        ->toBe('{"first":{"a":1},"second":{"a":1}}');
});

it('writes a shared scalar out at each place it appears', function () {
    $scalar = 'shared';

    expect(Serialized::make()->compact()->toJson(serialize(['x' => &$scalar, 'y' => &$scalar])))
        ->toBe('{"x":"shared","y":"shared"}');
});

it('resolves a reference written by hand', function () {
    expect(Serialized::make()->compact()->toJson('a:3:{i:0;s:1:"a";i:1;R:2;i:2;i:7;}'))
        ->toBe('["a","a",7]');
});

it('returns the real reference from toArray', function () {
    $shared = ['a' => 1];
    $value = Serialized::toArray(serialize(['first' => &$shared, 'second' => &$shared]));

    expect($value)->toBe(['first' => ['a' => 1], 'second' => ['a' => 1]]);
});

/**
 * A structure containing itself has no JSON form at all. It is refused on the way
 * down rather than left to json_encode(), which reports a recursion with no byte to
 * blame, and rather than left to the normalizer's own walk, which would not return.
 */
it('refuses a value that contains itself', function () {
    $loop = [];
    $loop['self'] = &$loop;

    $diagnostic = diagnosticFor(fn () => Serialized::toJson(serialize($loop)));

    expect($diagnostic->code)->toBe(DiagnosticCode::ContainsReference)
        ->and($diagnostic->reason)->toContain('never ends')
        ->and($diagnostic->offset)->toBeGreaterThan(0);
});

it('refuses a value that contains itself further down', function () {
    $inner = [];
    $inner['self'] = &$inner;

    $diagnostic = diagnosticFor(fn () => Serialized::toJson(serialize(['wrapper' => ['deep' => $inner]])));

    expect($diagnostic->code)->toBe(DiagnosticCode::ContainsReference);
});

it('still converts a payload holding no reference at all', function () {
    expect(Serialized::make()->compact()->toJson(serialize(['a' => 1, 'b' => 2])))
        ->toBe('{"a":1,"b":2}');
});

/**
 * The same value side by side is not a loop. Dropping each reference on the way back
 * up is what keeps the two apart.
 */
it('does not mistake a repeated sibling for a loop', function () {
    $shared = ['x' => ['y' => 1]];

    expect(Serialized::make()->compact()->toJson(serialize([&$shared, &$shared, &$shared])))
        ->toBe('[{"x":{"y":1}},{"x":{"y":1}},{"x":{"y":1}}]');
});
