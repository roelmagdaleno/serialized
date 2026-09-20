<?php

declare(strict_types=1);

use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Serialized;

it('carries a code a consumer can branch on instead of matching the prose', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}'));

    expect($diagnostic->code)->toBe(DiagnosticCode::LengthMismatch);
});

it('carries the facts a consumer needs to word its own message', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}'));

    $ownWording = match ($diagnostic->code) {
        DiagnosticCode::LengthMismatch => sprintf(
            'Se declararon %d bytes pero se encontraron %d en el byte %d.',
            $diagnostic->context['declaredByteLength'],
            $diagnostic->context['foundByteLength'],
            $diagnostic->offset,
        ),
        default => $diagnostic->reason,
    };

    expect($ownWording)->toBe('Se declararon 6 bytes pero se encontraron 5 en el byte 18.');
});

it('names the rejected class in the context of an unsafe payload', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('O:8:"stdClass":0:{}'));

    expect($diagnostic->code)->toBe(DiagnosticCode::DisallowedClass)
        ->and($diagnostic->context['className'])->toBe('stdClass');
});

it('still ships the default wording alongside the code and the context', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}'));

    expect($diagnostic->reason)->toBe('String length mismatch at offset 18: declared 6 bytes, found 5.')
        ->and($diagnostic->fix)->toBe('Change s:6 to s:5, or restore the missing bytes in the value.');
});
