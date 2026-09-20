<?php

declare(strict_types=1);

use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Exceptions\JsonEncodingException;
use Serialized\Exceptions\LimitExceededException;
use Serialized\Exceptions\SerializedException;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;

it('lets a caller catch every package exception through one interface', function (Throwable $exception) {
    expect($exception)->toBeInstanceOf(SerializedException::class);
})->with([
    'invalid' => fn () => InvalidSerializedDataException::lengthMismatch('s:6:"Chrom";', 2, 6, 5),
    'unsafe' => fn () => UnsafeSerializedDataException::disallowedClass('O:8:"stdClass":0:{}', 0, 'stdClass'),
    'unrepresentable' => fn () => UnrepresentableValueException::nonFiniteFloat('d:NAN;', 2, 'NAN'),
    'limit' => fn () => LimitExceededException::depth('a:0:{}', 65, 64),
    'json' => fn () => JsonEncodingException::encodingFailed(new JsonException('boom')),
]);

it('reports a string length mismatch with a byte-accurate diagnostic', function () {
    $exception = InvalidSerializedDataException::lengthMismatch('s:6:"Chrom";', 2, 6, 5);

    expect($exception->diagnostic()->offset)->toBe(2)
        ->and($exception->diagnostic()->code)->toBe(DiagnosticCode::LengthMismatch)
        ->and($exception->diagnostic()->context)->toBe([
            'declaredByteLength' => 6,
            'foundByteLength' => 5,
            'prefix' => 's',
        ])
        ->and($exception->diagnostic()->reason)->toContain('declared 6')
        ->and($exception->diagnostic()->reason)->toContain('found 5')
        ->and($exception->diagnostic()->fix)->toContain('s:5');
});

it('names the class and offset when rejecting an object', function () {
    $exception = UnsafeSerializedDataException::disallowedClass('O:8:"stdClass":0:{}', 0, 'stdClass');

    expect($exception->diagnostic()->offset)->toBe(0)
        ->and($exception->diagnostic()->code)->toBe(DiagnosticCode::DisallowedClass)
        ->and($exception->diagnostic()->context)->toBe(['className' => 'stdClass'])
        ->and($exception->diagnostic()->reason)->toContain('stdClass')
        ->and($exception->diagnostic()->fix)->toContain('allowClasses');
});

it('names the limit, the configured value and the actual value', function () {
    $exception = LimitExceededException::depth('a:0:{}', 65, 64);

    expect($exception->diagnostic()->code)->toBe(DiagnosticCode::MaxDepthExceeded)
        ->and($exception->diagnostic()->context)->toBe(['actualDepth' => 65, 'configuredLimit' => 64])
        ->and($exception->diagnostic()->reason)->toContain('65')
        ->and($exception->diagnostic()->reason)->toContain('64')
        ->and($exception->diagnostic()->fix)->toContain('withMaxDepth');
});

it('builds the message from the diagnostic, snippet included', function () {
    $message = InvalidSerializedDataException::lengthMismatch('s:6:"Chrom";', 2, 6, 5)->getMessage();

    expect($message)->toContain('offset 2')
        ->and($message)->toContain('s:6:"Chrom";')
        ->and($message)->toContain('^')
        ->and($message)->toContain('Fix:');
});

it('has no diagnostic when the failure cannot be traced to a byte', function () {
    expect(JsonEncodingException::encodingFailed(new JsonException('boom'))->diagnostic())->toBeNull();
});

it('wraps the underlying JsonException as the previous exception', function () {
    $previous = new JsonException('Malformed UTF-8 characters');
    $exception = JsonEncodingException::encodingFailed($previous);

    expect($exception->getPrevious())->toBe($previous)
        ->and($exception->getMessage())->toContain('Malformed UTF-8 characters');
});

it('exposes a diagnostic on every exception that has a payload offset', function () {
    $diagnostic = UnrepresentableValueException::nonUtf8String("s:1:\"\xff\";", 6)->diagnostic();

    expect($diagnostic)->toBeInstanceOf(Diagnostic::class)
        ->and($diagnostic->code)->toBe(DiagnosticCode::NonUtf8String)
        ->and($diagnostic->offset)->toBe(6)
        ->and($diagnostic->fix)->toContain('Base64');
});
