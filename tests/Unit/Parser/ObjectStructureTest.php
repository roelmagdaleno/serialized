<?php

declare(strict_types=1);

use Serialized\Tokenizer\TokenType;

it('records every class the payload names, with its offset', function () {
    $parsed = parse('O:8:"stdClass":1:{s:1:"a";i:1;}');

    expect($parsed->classNames)
        ->toBe([['className' => 'stdClass', 'offset' => 0, 'type' => TokenType::Object]]);
});

it('records classes nested inside arrays and other objects', function () {
    $parsed = parse('a:1:{i:0;O:8:"stdClass":1:{s:1:"a";O:8:"DateTime":0:{}}}');

    expect($parsed->classNames)->toBe([
        ['className' => 'stdClass', 'offset' => 9, 'type' => TokenType::Object],
        ['className' => 'DateTime', 'offset' => 35, 'type' => TokenType::Object],
    ]);
});

it('records a custom-serialized object as a named class', function () {
    expect(parse('C:8:"stdClass":4:{data}')->classNames)
        ->toBe([['className' => 'stdClass', 'offset' => 0, 'type' => TokenType::CustomObject]]);
});

it('names the object, not an array, when its property count is wrong', function () {
    $diagnostic = diagnosticFor(fn () => parse('O:8:"stdClass":2:{s:1:"a";i:1;}'));

    expect($diagnostic->reason)->toContain('object')
        ->and($diagnostic->reason)->not->toContain('array')
        ->and($diagnostic->fix)->toContain('O:8:"stdClass":1')
        ->and($diagnostic->fix)->not->toContain('a:1');
});

it('names the object header to change when it holds more than it declares', function () {
    $diagnostic = diagnosticFor(fn () => parse('O:8:"stdClass":1:{s:1:"a";i:1;s:1:"b";i:2;}'));

    expect($diagnostic->fix)->toContain('O:8:"stdClass":1')
        ->and($diagnostic->fix)->toContain('O:8:"stdClass":2');
});

it('calls a bad key in an object a property name, not an array key', function () {
    $diagnostic = diagnosticFor(fn () => parse('O:8:"stdClass":1:{N;i:1;}'));

    expect($diagnostic->reason)->toContain('property name')
        ->and($diagnostic->reason)->not->toContain('array key')
        ->and($diagnostic->fix)->toContain('property name');
});

it('names the object when it is never closed', function () {
    $diagnostic = diagnosticFor(fn () => parse('O:8:"stdClass":1:{s:1:"a";i:1;'));

    expect($diagnostic->reason)->toContain('object')
        ->and($diagnostic->reason)->not->toContain('array');
});

it('records where the first reference sits', function (string $payload) {
    expect(parse($payload)->referenceOffset)->toBe(15);
})->with([
    'back reference' => ['a:2:{i:0;N;i:1;R:2;}'],
    'value reference' => ['a:2:{i:0;N;i:1;r:2;}'],
]);

it('reports no references for a payload without them', function () {
    expect(parse(chromePayload())->referenceOffset)->toBeNull();
});

it('counts an object as one level of nesting, like an array', function () {
    expect(parse('O:8:"stdClass":1:{s:1:"a";i:1;}')->depth)->toBe(2)
        ->and(parse('a:1:{i:0;O:8:"stdClass":0:{}}')->depth)->toBe(3);
});

it('rejects an object whose property count does not match its contents', function () {
    $diagnostic = diagnosticFor(fn () => parse('O:8:"stdClass":2:{s:1:"a";i:1;}'));

    expect($diagnostic->offset)->toBe(0)
        ->and($diagnostic->reason)->toContain('2');
});

it('rejects a non-scalar property name', function () {
    expect(diagnosticFor(fn () => parse('O:8:"stdClass":1:{N;i:1;}'))->offset)->toBe(18);
});
