<?php

declare(strict_types=1);

it('measures the shape of a flat array', function () {
    $parsed = parse('a:2:{i:0;s:1:"a";i:1;s:1:"b";}');

    expect($parsed->depth)->toBe(2)
        ->and($parsed->elementCount)->toBe(5);
});

it('measures the shape of the Chrome payload', function () {
    $parsed = parse(chromePayload());

    expect($parsed->depth)->toBe(2)
        ->and($parsed->elementCount)->toBe(21);
});

it('counts depth from the deepest branch', function () {
    expect(parse('a:1:{i:0;a:1:{i:0;a:0:{}}}')->depth)->toBe(4)
        ->and(parse('a:2:{i:0;a:0:{}i:1;i:9;}')->depth)->toBe(3);
});

it('accepts an empty array', function () {
    expect(parse('a:0:{}')->elementCount)->toBe(1);
});

it('accepts both integer and string keys', function () {
    expect(parse('a:2:{i:0;i:1;s:1:"k";i:2;}')->elementCount)->toBe(5);
});

it('rejects a structurally broken array', function (string $payload, int $offset) {
    expect(phpRejects($payload))->toBeTrue();

    expect(diagnosticFor(fn () => parse($payload))->offset)->toBe($offset);
})->with([
    'count higher than actual' => ['a:2:{i:0;N;}', 0],
    'count lower than actual' => ['a:1:{i:0;N;i:1;N;}', 0],
    'unclosed array' => ['a:1:{i:0;N;', 0],
    'close without an array' => ['N;}', 2],
    'non scalar key' => ['a:1:{a:0:{}N;}', 5],
    'null key' => ['a:1:{N;N;}', 5],
    'float key' => ['a:1:{d:1.5;N;}', 5],
    'boolean key' => ['a:1:{b:1;N;}', 5],
    'trailing bytes after an array' => ['a:0:{}i:1;', 6],
]);

it('names the declared and actual element counts', function () {
    $diagnostic = diagnosticFor(fn () => parse('a:2:{i:0;N;}'));

    expect($diagnostic->reason)->toContain('2')
        ->and($diagnostic->reason)->toContain('1')
        ->and($diagnostic->fix)->toContain('a:1');
});

it('calls a bad key in an array an array key', function () {
    $diagnostic = diagnosticFor(fn () => parse('a:1:{N;N;}'));

    expect($diagnostic->reason)->toContain('array key')
        ->and($diagnostic->reason)->not->toContain('property name')
        ->and($diagnostic->fix)->toContain('array key');
});

it('does not exhaust the stack on a deeply nested payload', function () {
    $payload = str_repeat('a:1:{i:0;', 10_000).'N;'.str_repeat('}', 10_000);

    expect(parse($payload)->depth)->toBe(10_001);
});
