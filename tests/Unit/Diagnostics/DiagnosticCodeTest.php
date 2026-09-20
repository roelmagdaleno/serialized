<?php

declare(strict_types=1);

use Serialized\Diagnostics\DiagnosticCode;

/**
 * A representative context for every code, as the exceptions build it.
 *
 * A match rather than a lookup array so that adding a case to the enum without
 * covering it here is a static-analysis failure rather than a silent gap.
 *
 * @return array<string, string|int|bool|null>
 */
function contextFor(DiagnosticCode $code): array
{
    return match ($code) {
        DiagnosticCode::EmptyPayload,
        DiagnosticCode::UnbalancedClose,
        DiagnosticCode::ValueOverrunsDeclaredLength,
        DiagnosticCode::TrailingBytes,
        DiagnosticCode::ContainsReference,
        DiagnosticCode::NonUtf8String,
        DiagnosticCode::UnusablePropertyName => [],
        DiagnosticCode::UnknownTypePrefix => ['prefix' => 'z'],
        DiagnosticCode::TruncatedPayload => ['expected' => ';'],
        DiagnosticCode::UnexpectedByte => ['expected' => ':', 'found' => ','],
        DiagnosticCode::MalformedValue => ['typeLabel' => 'integer', 'literal' => '4x'],
        DiagnosticCode::MalformedLength => ['literal' => '-1'],
        DiagnosticCode::MalformedElementCount => ['literal' => '-1'],
        DiagnosticCode::ElementCountMismatch => [
            'structureLabel' => 'array',
            'className' => null,
            'declaredCount' => 2,
            'actualCount' => 1,
        ],
        DiagnosticCode::ImpossibleElementCount => ['declaredCount' => '9999', 'remainingByteCount' => 4],
        DiagnosticCode::UnclosedStructure => ['structureLabel' => 'array'],
        DiagnosticCode::NonScalarKey => ['keyTypeLabel' => 'array', 'keySlot' => 'an array key'],
        DiagnosticCode::RejectedByPhp => ['phpMessage' => 'Error at offset 4 of 8 bytes'],
        DiagnosticCode::LengthMismatch => [
            'declaredByteLength' => 6,
            'foundByteLength' => 5,
            'prefix' => 's',
        ],
        DiagnosticCode::DisallowedClass,
        DiagnosticCode::UnloadableClass,
        DiagnosticCode::UnrestorableClass => ['className' => 'App\Models\Money'],
        DiagnosticCode::NonBackedEnum => ['caseName' => 'Suit::Hearts'],
        DiagnosticCode::NonFiniteFloat => ['literal' => 'NAN'],
        DiagnosticCode::MaxBytesExceeded => ['actualBytes' => 2_000_000, 'configuredLimit' => 1_000_000],
        DiagnosticCode::MaxDepthExceeded => ['actualDepth' => 65, 'configuredLimit' => 64],
        DiagnosticCode::MaxElementsExceeded => ['configuredLimit' => 1_000],
    };
}

it('words a reason and a fix for every code', function (DiagnosticCode $code) {
    $reason = $code->reason(12, contextFor($code));
    $fix = $code->fix(12, contextFor($code));

    expect($reason)->not->toBeEmpty()
        ->and($reason)->not->toContain('%')
        ->and($fix)->not->toBeEmpty()
        ->and($fix)->not->toContain('%');
})->with(DiagnosticCode::cases());

it('keeps a stable string value for every code', function (DiagnosticCode $code) {
    expect($code->value)->toMatch('/^[a-z0-9_]+$/');
})->with(DiagnosticCode::cases());

it('interpolates the facts its context carries', function () {
    $code = DiagnosticCode::LengthMismatch;
    $context = ['declaredByteLength' => 6, 'foundByteLength' => 5, 'prefix' => 's'];

    expect($code->reason(2, $context))
        ->toBe('String length mismatch at offset 2: declared 6 bytes, found 5.')
        ->and($code->fix(2, $context))
        ->toBe('Change s:6 to s:5, or restore the missing bytes in the value.');
});

it('names the numbers instead of a type letter when a length has no prefix to quote', function () {
    $fix = DiagnosticCode::LengthMismatch->fix(2, [
        'declaredByteLength' => 8,
        'foundByteLength' => 7,
        'prefix' => null,
    ]);

    expect($fix)->toBe('Change the declared length from 8 to 7, or restore the missing bytes.');
});

it('spells an array header when a mismatched structure has no class name', function () {
    $fix = DiagnosticCode::ElementCountMismatch->fix(0, [
        'structureLabel' => 'array',
        'className' => null,
        'declaredCount' => 2,
        'actualCount' => 1,
    ]);

    expect($fix)->toBe('Change a:2 to a:1, or correct the array contents.');
});

it('spells an object header when a mismatched structure names a class', function () {
    $fix = DiagnosticCode::ElementCountMismatch->fix(0, [
        'structureLabel' => 'object',
        'className' => 'Money',
        'declaredCount' => 2,
        'actualCount' => 1,
    ]);

    expect($fix)->toBe('Change O:5:"Money":2 to O:5:"Money":1, or correct the object contents.');
});

it('names the slot a rejected key was used in', function () {
    $code = DiagnosticCode::NonScalarKey;

    expect($code->reason(4, ['keyTypeLabel' => 'array', 'keySlot' => 'an array key']))
        ->toContain('used as an array key')
        ->and($code->fix(4, ['keyTypeLabel' => 'array', 'keySlot' => 'a property name']))
        ->toContain('can be a property name');
});
