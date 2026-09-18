<?php

declare(strict_types=1);

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\Tokenizer;
use Serialized\Tokenizer\TokenType;

/**
 * @return list<Token>
 */
function tokenize(string $payload): array
{
    return new Tokenizer()->tokenize($payload);
}

it('tokenizes every scalar type', function (string $payload, TokenType $type, string $literal) {
    $tokens = tokenize($payload);

    expect($tokens)->toHaveCount(1)
        ->and($tokens[0]->type)->toBe($type)
        ->and($tokens[0]->offset)->toBe(0)
        ->and($tokens[0]->literal)->toBe($literal)
        ->and($tokens[0]->raw)->toBe($payload);
})->with([
    'null' => ['N;', TokenType::Null, ''],
    'false' => ['b:0;', TokenType::Boolean, '0'],
    'true' => ['b:1;', TokenType::Boolean, '1'],
    'integer' => ['i:42;', TokenType::Integer, '42'],
    'negative integer' => ['i:-42;', TokenType::Integer, '-42'],
    'zero' => ['i:0;', TokenType::Integer, '0'],
    'float' => ['d:1.5;', TokenType::Float, '1.5'],
    'negative float' => ['d:-1.5;', TokenType::Float, '-1.5'],
    'exponent float' => ['d:1.0E+25;', TokenType::Float, '1.0E+25'],
    'nan' => ['d:NAN;', TokenType::Float, 'NAN'],
    'infinity' => ['d:INF;', TokenType::Float, 'INF'],
    'negative infinity' => ['d:-INF;', TokenType::Float, '-INF'],
    'string' => ['s:6:"Chrome";', TokenType::String, 'Chrome'],
    'empty string' => ['s:0:"";', TokenType::String, ''],
]);

it('records the declared byte length of a string', function () {
    expect(tokenize('s:6:"Chrome";')[0]->declaredLength)->toBe(6);
});

it('slices a string by its declared length, not by scanning for the closing quote', function () {
    $token = tokenize('s:4:"a";b";')[0];

    expect($token->literal)->toBe('a";b')
        ->and($token->raw)->toBe('s:4:"a";b";')
        ->and(unserialize('s:4:"a";b";'))->toBe('a";b');
});

it('keeps a multibyte string intact and counts its length in bytes', function () {
    $token = tokenize('s:4:"héo";')[0];

    expect($token->literal)->toBe('héo')
        ->and($token->declaredLength)->toBe(4)
        ->and(strlen($token->literal))->toBe(4);
});

it('preserves the raw bytes of a binary string', function () {
    $token = tokenize("s:3:\"a\x00b\";")[0];

    expect($token->literal)->toBe("a\x00b");
});

it('reads several scalars in sequence with byte-accurate offsets', function () {
    $tokens = tokenize('i:1;s:1:"a";N;');

    expect($tokens)->toHaveCount(3)
        ->and($tokens[0]->offset)->toBe(0)
        ->and($tokens[1]->offset)->toBe(4)
        ->and($tokens[2]->offset)->toBe(12);
});

it('rejects a malformed payload with a byte-accurate diagnostic', function (string $payload, int $offset) {
    expect(phpRejects($payload))->toBeTrue();

    $diagnostic = diagnosticFor(fn () => tokenize($payload));

    expect($diagnostic->offset)->toBe($offset)
        ->and($diagnostic->reason)->not->toBeEmpty()
        ->and($diagnostic->fix)->not->toBeEmpty();
})->with([
    'empty payload' => ['', 0],
    'unknown type prefix' => ['x:1;', 0],
    'truncated null' => ['N', 1],
    'missing colon' => ['i42;', 1],
    'truncated integer' => ['i:42', 4],
    'non numeric integer' => ['i:4x2;', 2],
    'empty integer' => ['i:;', 2],
    'invalid boolean' => ['b:2;', 2],
    'non numeric float' => ['d:1.2.3;', 2],
    'non numeric string length' => ['s:x:"a";', 2],
    'negative string length' => ['s:-1:"a";', 2],
    'string missing opening quote' => ['s:1:a";', 4],
    'string shorter than declared' => ['s:6:"Chrom";', 2],
    'string missing closing quote' => ['s:1:"ab;', 6],
    'string missing terminator' => ['s:1:"a"', 7],
    'truncated string' => ['s:6:"Chr', 2],
    'string length missing its colon' => ['s:6', 3],
    'string longer than the payload but terminated' => ['s:9:"Chrom";', 2],
    'string with nothing but a terminator left' => ['s:5:";', 2],
    'empty string cut off at the quote' => ['s:0:"', 5],
    'integer cut off after the colon' => ['i:', 2],
]);

it('reports the declared and actual length when a string is short', function () {
    $diagnostic = diagnosticFor(fn () => tokenize('s:6:"Chrom";'));

    expect($diagnostic->reason)->toContain('declared 6')
        ->and($diagnostic->reason)->toContain('found 5')
        ->and($diagnostic->fix)->toContain('s:5');
});

it('counts the bytes actually present when a string runs past the payload', function (string $payload, int $actual) {
    expect(diagnosticFor(fn () => tokenize($payload))->reason)->toContain("found {$actual}");
})->with([
    'terminator present' => ['s:9:"Chrom";', 5],
    'nothing but a terminator' => ['s:5:";', 1],
    'no terminator at all' => ['s:6:"Chr', 3],
]);

it('rejects the S: escaped-string form deliberately, though PHP accepts it', function () {
    expect(fn () => tokenize('S:1:"a";'))->toThrow(InvalidSerializedDataException::class);
});
