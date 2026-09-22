<?php

declare(strict_types=1);

use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Serialized;

/**
 * The `S` format writes a string's bytes as `\XX` escapes.
 *
 * PHP emits it for strings holding bytes it would rather not print and reads it back as
 * an ordinary string, so a payload using it is a payload this package must convert.
 */
it('converts a string whose bytes are written as escapes', function (string $payload, string $json) {
    expect(Serialized::make()->compact()->toJson($payload))->toBe($json);
})->with([
    'one escape' => ['S:5:"\\68ello";', '"hello"'],
    'every byte escaped' => ['S:2:"\\68\\69";', '"hi"'],
    'no escape at all' => ['S:5:"hello";', '"hello"'],
    'lowercase hex' => ['S:1:"\\7a";', '"z"'],
    'uppercase hex' => ['S:1:"\\7A";', '"z"'],
    'empty value' => ['S:0:"";', '""'],
]);

/**
 * PHP 8.4 deprecated reading the `S` format, so the payload PHP sees must spell it as `s`.
 */
it('raises no deprecation for an escaped string', function (string $payload) {
    expect(errorLeakedBy(fn () => Serialized::toJson($payload)))->toBeNull()
        ->and(errorLeakedBy(fn () => Serialized::toArray($payload)))->toBeNull();
})->with([
    'a value' => ['S:5:"\\68ello";'],
    'an array key' => ['a:1:{S:3:"\\61bc";S:1:"x";}'],
]);

it('reads an escaped string as an array key and as a value', function () {
    expect(Serialized::make()->compact()->toJson('a:1:{S:3:"\\61bc";S:1:"x";}'))->toBe('{"abc":"x"}');
});

it('counts the declared length in decoded bytes, not written ones', function () {
    expect(Serialized::toArray('S:5:"\\68ello";'))->toBe('hello');
});

/**
 * The decoded value is what the UTF-8 rule has to judge: `\ff` is printable ASCII on the
 * page and an invalid UTF-8 byte once decoded.
 */
it('rejects an escaped string that decodes to non-UTF-8 bytes', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('S:1:"\\ff";'));

    expect($diagnostic->code)->toBe(DiagnosticCode::NonUtf8String);
});

it('rejects an escape that is not two hexadecimal digits', function () {
    $payload = 'S:2:"\\zz";';
    $diagnostic = diagnosticFor(fn () => Serialized::toJson($payload));

    expect($diagnostic->code)->toBe(DiagnosticCode::MalformedValue)
        ->and($diagnostic->context['literal'])->toBe('\\zz')
        ->and(phpRejects($payload))->toBeTrue();
});

it('names the S prefix when the declared length is wrong', function (string $payload, int $declared, int $found) {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson($payload));

    expect($diagnostic->code)->toBe(DiagnosticCode::LengthMismatch)
        ->and($diagnostic->context['declaredByteLength'])->toBe($declared)
        ->and($diagnostic->context['foundByteLength'])->toBe($found)
        ->and($diagnostic->context['prefix'])->toBe('S')
        ->and($diagnostic->fix)->toContain('S:')
        ->and(phpRejects($payload))->toBeTrue();
})->with([
    'declares more than it spells' => ['S:5:"\\68el";', 5, 3],
    'declares fewer than it spells' => ['S:2:"\\68ello";', 2, 5],
    'runs out before the declared length' => ['S:5:"hi', 5, 2],
]);

it('reports an escaped string with a broken header', function (string $payload, DiagnosticCode $code) {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson($payload));

    expect($diagnostic->code)->toBe($code)
        ->and(phpRejects($payload))->toBeTrue();
})->with([
    'no first colon' => ['S;5:"hello";', DiagnosticCode::UnexpectedByte],
    'no second colon' => ['S:5;"hello";', DiagnosticCode::TruncatedPayload],
    'no opening quote' => ['S:5:hello";', DiagnosticCode::UnexpectedByte],
    'no terminator' => ['S:5:"hello"', DiagnosticCode::TruncatedPayload],
    'never closes' => ['S:5:"\\68ello', DiagnosticCode::TruncatedPayload],
    'no closing quote and no terminator' => ['S:2:"hixx', DiagnosticCode::UnexpectedByte],
    'wrong byte after the value' => ['S:2:"hi"x', DiagnosticCode::UnexpectedByte],
    'malformed length' => ['S:x:"hello";', DiagnosticCode::MalformedLength],
]);
