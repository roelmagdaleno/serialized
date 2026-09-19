<?php

declare(strict_types=1);

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Serialized;
use Tests\Support\DriftedClass;
use Tests\Support\Suit;

/**
 * A payload whose object carries a property the class no longer declares.
 */
function driftedPayload(): string
{
    return 'O:26:"Tests\Support\DriftedClass":2:{s:6:"amount";i:5;s:8:"currency";s:3:"USD";}';
}

it('converts a payload whose class has lost a property', function () {
    $json = Serialized::make()
        ->allowClasses([DriftedClass::class])
        ->compact()
        ->toJson(driftedPayload());

    expect($json)->toBe('{"amount":5,"currency":"USD"}');
});

it('agrees with itself about a payload PHP can read', function () {
    $converter = Serialized::make()->allowClasses([DriftedClass::class]);

    expect($converter->isValid(driftedPayload()))->toBeTrue()
        ->and($converter->tryToJson(driftedPayload()))->not->toBeNull();
});

/**
 * A payload naming an enum case the enum no longer has.
 *
 * Every stage of the package accepts it — the enum is allowed, loadable and backed — so
 * unserialize() is the first to object, which is the path this exception exists for.
 */
function droppedEnumCasePayload(): string
{
    $case = Suit::class.':Nope';

    return 'E:'.strlen($case).':"'.$case.'";';
}

it('still reports a payload unserialize() itself refuses', function () {
    expect(fn () => Serialized::make()->allowClasses([Suit::class])->toJson(droppedEnumCasePayload()))
        ->toThrow(InvalidSerializedDataException::class);
});

it('carries what PHP said into the diagnostic', function () {
    $diagnostic = diagnosticFor(
        fn () => Serialized::make()->allowClasses([Suit::class])->toJson(droppedEnumCasePayload()),
    );

    expect($diagnostic->reason)->toContain('unserialize()');
});
