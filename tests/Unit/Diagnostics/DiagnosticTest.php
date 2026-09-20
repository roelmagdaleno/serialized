<?php

declare(strict_types=1);

use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;

it('exposes the code, payload, offset and context', function () {
    $diagnostic = new Diagnostic(
        code: DiagnosticCode::LengthMismatch,
        payload: 'a:1:{i:0;N;}',
        offset: 5,
        context: ['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's'],
    );

    expect($diagnostic->code)->toBe(DiagnosticCode::LengthMismatch)
        ->and($diagnostic->payload)->toBe('a:1:{i:0;N;}')
        ->and($diagnostic->offset)->toBe(5)
        ->and($diagnostic->context)->toBe(['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's']);
});

it('derives the reason and the fix from its code', function () {
    $context = ['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's'];
    $diagnostic = new Diagnostic(DiagnosticCode::LengthMismatch, 's:6:"Chrom";', 2, $context);

    expect($diagnostic->reason)->toBe(DiagnosticCode::LengthMismatch->reason(2, $context))
        ->and($diagnostic->fix)->toBe(DiagnosticCode::LengthMismatch->fix(2, $context));
});

it('carries an empty context for a failure with no facts to report', function () {
    expect(new Diagnostic(DiagnosticCode::UnbalancedClose, 'a:0:{}}', 6)->context)->toBe([]);
});

it('rejects a negative offset', function () {
    new Diagnostic(DiagnosticCode::UnbalancedClose, 'N;', -1);
})->throws(InvalidArgumentException::class);

it('rejects an offset beyond one past the end of the payload', function () {
    new Diagnostic(DiagnosticCode::UnbalancedClose, 'N;', 3);
})->throws(InvalidArgumentException::class);

it('accepts an offset exactly one past the end for a truncated payload', function () {
    expect(new Diagnostic(DiagnosticCode::TruncatedPayload, 'N;', 2, ['expected' => ';'])->offset)->toBe(2);
});
