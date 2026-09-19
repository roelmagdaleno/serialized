<?php

declare(strict_types=1);

use Serialized\Parser\ParsedPayload;
use Serialized\Parser\Parser;
use Serialized\Tokenizer\Tokenizer;

function parse(string $payload): ParsedPayload
{
    return new Parser()->parse($payload, new Tokenizer()->tokenize($payload));
}

it('reports one element at depth one for a scalar payload', function (string $payload) {
    $parsed = parse($payload);

    expect($parsed->depth)->toBe(1)
        ->and($parsed->elementCount)->toBe(1)
        ->and($parsed->classNames)->toBe([])
        ->and($parsed->referenceOffset)->toBeNull();
})->with(['N;', 'b:1;', 'i:42;', 'd:1.5;', 's:6:"Chrome";']);

it('rejects bytes trailing a complete value', function () {
    expect(phpRejects('i:1;i:2;'))->toBeTrue();

    $diagnostic = diagnosticFor(fn () => parse('i:1;i:2;'));

    expect($diagnostic->offset)->toBe(4)
        ->and($diagnostic->reason)->toContain('offset 4')
        ->and($diagnostic->fix)->not->toBeEmpty();
});

it('carries only metadata, never unserialized values', function () {
    $properties = array_keys(get_object_vars(parse('s:6:"Chrome";')));

    expect($properties)->toBe(['depth', 'elementCount', 'classNames', 'referenceOffset']);
});
