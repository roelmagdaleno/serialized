<?php

declare(strict_types=1);

use Serialized\Tokenizer\TokenType;

it('tokenizes an array as a header plus its contents plus a close', function () {
    $tokens = tokenize('a:1:{i:0;N;}');

    expect($tokens)->toHaveCount(4)
        ->and($tokens[0]->type)->toBe(TokenType::Array)
        ->and($tokens[0]->declaredCount)->toBe(1)
        ->and($tokens[0]->offset)->toBe(0)
        ->and($tokens[1]->type)->toBe(TokenType::Integer)
        ->and($tokens[2]->type)->toBe(TokenType::Null)
        ->and($tokens[3]->type)->toBe(TokenType::Close)
        ->and($tokens[3]->offset)->toBe(11);
});

it('tokenizes an empty array', function () {
    $tokens = tokenize('a:0:{}');

    expect($tokens)->toHaveCount(2)
        ->and($tokens[0]->declaredCount)->toBe(0);
});

it('tokenizes nested arrays with byte-accurate offsets', function () {
    $tokens = tokenize('a:1:{i:0;a:1:{i:0;i:7;}}');

    expect($tokens)->toHaveCount(7)
        ->and($tokens[2]->type)->toBe(TokenType::Array)
        ->and($tokens[2]->offset)->toBe(9)
        ->and($tokens[5]->type)->toBe(TokenType::Close)
        ->and($tokens[5]->offset)->toBe(22)
        ->and($tokens[6]->type)->toBe(TokenType::Close)
        ->and($tokens[6]->offset)->toBe(23);
});

it('rejects a malformed array header', function (string $payload, int $offset) {
    expect(phpRejects($payload))->toBeTrue();

    expect(diagnosticFor(fn () => tokenize($payload))->offset)->toBe($offset);
})->with([
    'non numeric count' => ['a:x:{}', 2],
    'negative count' => ['a:-1:{}', 2],
    'missing brace' => ['a:1:i:0;', 4],
    'missing colon after count' => ['a:1', 3],
    'missing first colon' => ['a1:{}', 1],
]);
