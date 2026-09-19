<?php

declare(strict_types=1);

use Serialized\Tokenizer\TokenType;

it('tokenizes an object header with its class name and property count', function () {
    $tokens = tokenize('O:8:"stdClass":1:{s:1:"a";i:1;}');

    expect($tokens[0]->type)->toBe(TokenType::Object)
        ->and($tokens[0]->className)->toBe('stdClass')
        ->and($tokens[0]->declaredCount)->toBe(1)
        ->and($tokens[0]->raw())->toBe('O:8:"stdClass":1:{')
        ->and($tokens[1]->offset)->toBe(18);
});

it('tokenizes a namespaced class name', function () {
    $tokens = tokenize('O:16:"App\Models\Money":0:{}');

    expect($tokens[0]->className)->toBe('App\Models\Money');
});

it('tokenizes an empty object', function () {
    expect(tokenize('O:8:"stdClass":0:{}'))->toHaveCount(2);
});

it('tokenizes a custom-serialized object', function () {
    $tokens = tokenize('C:8:"stdClass":4:{data}');

    expect($tokens[0]->type)->toBe(TokenType::CustomObject)
        ->and($tokens[0]->className)->toBe('stdClass')
        ->and($tokens[0]->literal())->toBe('data')
        ->and($tokens)->toHaveCount(1);
});

it('tokenizes both reference forms', function (string $payload, TokenType $type) {
    $tokens = tokenize($payload);

    expect($tokens[4]->type)->toBe($type)
        ->and($tokens[4]->literal())->toBe('2');
})->with([
    'back reference' => ['a:2:{i:0;N;i:1;R:2;}', TokenType::Reference],
    'value reference' => ['a:2:{i:0;N;i:1;r:2;}', TokenType::ValueReference],
]);

it('tokenizes an enum token', function () {
    $tokens = tokenize('E:11:"Suit:Hearts";');

    expect($tokens[0]->type)->toBe(TokenType::Enum)
        ->and($tokens[0]->literal())->toBe('Suit:Hearts')
        ->and($tokens[0]->className)->toBe('Suit');
});

it('names the object prefix in a class-name length fix', function () {
    expect(diagnosticFor(fn () => tokenize('O:4:"stdClass":0:{}'))->fix)->toContain('O:8');
});

it('rejects a malformed object header', function (string $payload, int $offset) {
    expect(diagnosticFor(fn () => tokenize($payload))->offset)->toBe($offset);
})->with([
    'non numeric class length' => ['O:x:"stdClass":0:{}', 2],
    'class name length mismatch' => ['O:4:"stdClass":0:{}', 2],
    'custom object body longer than the payload' => ['C:8:"stdClass":9:{data}', 15],
    'missing brace' => ['O:8:"stdClass":0:', 17],
    'non numeric property count' => ['O:8:"stdClass":x:{}', 15],
    'missing class quote' => ['O:8:stdClass":0:{}', 4],
    'class name length missing its colon' => ['O:8', 3],
    'object missing its property count' => ['O:8:"stdClass"', 14],
    'custom object missing its body length' => ['C:8:"stdClass"', 14],
    'class name cut off at the quote' => ['O:8:"', 2],
]);
