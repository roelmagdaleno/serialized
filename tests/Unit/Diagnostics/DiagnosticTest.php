<?php

declare(strict_types=1);

use Serialized\Diagnostics\Diagnostic;

it('exposes the payload, offset, reason and fix', function () {
    $diagnostic = new Diagnostic(
        payload: 'a:1:{i:0;N;}',
        offset: 5,
        reason: 'Unexpected token.',
        fix: 'Remove it.',
    );

    expect($diagnostic->payload)->toBe('a:1:{i:0;N;}')
        ->and($diagnostic->offset)->toBe(5)
        ->and($diagnostic->reason)->toBe('Unexpected token.')
        ->and($diagnostic->fix)->toBe('Remove it.');
});

it('rejects a negative offset', function () {
    new Diagnostic(payload: 'N;', offset: -1, reason: 'r', fix: 'f');
})->throws(InvalidArgumentException::class);

it('rejects an offset beyond one past the end of the payload', function () {
    new Diagnostic(payload: 'N;', offset: 3, reason: 'r', fix: 'f');
})->throws(InvalidArgumentException::class);

it('accepts an offset exactly one past the end for a truncated payload', function () {
    expect(new Diagnostic(payload: 'N;', offset: 2, reason: 'r', fix: 'f')->offset)->toBe(2);
});
