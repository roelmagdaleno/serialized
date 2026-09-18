<?php

declare(strict_types=1);

use Serialized\Tokenizer\TokenType;

it('labels every token type for diagnostics', function (TokenType $type) {
    expect($type->label())->not->toBeEmpty();
})->with(TokenType::cases());

it('accepts only integers and strings as array keys', function () {
    $keyable = array_values(array_filter(TokenType::cases(), static fn (TokenType $type): bool => $type->isValidArrayKey()));

    expect($keyable)->toEqualCanonicalizing([TokenType::Integer, TokenType::String]);
});

it('treats arrays and objects as the types that open a brace', function () {
    $opening = array_values(array_filter(TokenType::cases(), static fn (TokenType $type): bool => $type->opensStructure()));

    expect($opening)->toEqualCanonicalizing([TokenType::Array, TokenType::Object]);
});

it('treats both R and r as references', function () {
    $references = array_values(array_filter(TokenType::cases(), static fn (TokenType $type): bool => $type->isReference()));

    expect($references)->toEqualCanonicalizing([TokenType::Reference, TokenType::ValueReference]);
});
