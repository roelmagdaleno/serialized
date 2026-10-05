<?php

declare(strict_types=1);

use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Serialized;
use Serialized\SerializedConverter;
use Tests\Support\AbstractLedger;
use Tests\Support\Account;
use Tests\Support\LegacyBox;
use Tests\Support\MagicMethodRecorder;
use Tests\Support\Money;
use Tests\Support\SavingsAccount;

/**
 * A compact converter that reads every object as data.
 */
function dataConverter(): SerializedConverter
{
    return Serialized::make()->objectsAsData()->compact();
}

it('converts an object of a class it was never told about', function (string $payload, string $json) {
    expect(dataConverter()->toJson($payload))->toBe($json);
})->with([
    'unknown class' => [
        'O:4:"User":2:{s:4:"name";s:3:"Ada";s:5:"email";s:15:"ada@example.com";}',
        '{"name":"Ada","email":"ada@example.com"}',
    ],
    'inside an array' => [
        'a:2:{s:4:"user";O:4:"User":1:{s:4:"name";s:3:"Ada";}s:6:"active";b:1;}',
        '{"user":{"name":"Ada"},"active":true}',
    ],
    'inside another object' => [
        'O:4:"User":2:{s:4:"name";s:3:"Ada";s:7:"address";O:7:"Address":1:{s:4:"city";s:6:"London";}}',
        '{"name":"Ada","address":{"city":"London"}}',
    ],
    'empty object' => ['O:4:"User":0:{}', '{}'],
    'stdClass' => ['O:8:"stdClass":1:{s:4:"name";s:3:"Ada";}', '{"name":"Ada"}'],
    'numeric property names' => ['O:4:"User":1:{s:1:"0";s:4:"zero";}', '{"0":"zero"}'],
]);

it('rejects the same object when objects are not read as data', function () {
    expect(fn () => Serialized::toJson('O:4:"User":0:{}'))
        ->toThrow(UnsafeSerializedDataException::class);
});

it('never builds the class a payload names, nor runs its magic methods', function () {
    MagicMethodRecorder::$invoked = [];
    $payload = serialize(new MagicMethodRecorder);
    MagicMethodRecorder::$invoked = [];

    $json = dataConverter()->toJson($payload);
    $value = dataConverter()->toArray($payload);
    unset($value);
    gc_collect_cycles();

    expect($json)->toBe('{"command":"rm -rf /"}')
        ->and(MagicMethodRecorder::$invoked)->toBe([]);
});

it('never asks an autoloader for the class a payload names', function () {
    $requested = [];
    $autoloader = static function (string $className) use (&$requested): void {
        $requested[] = $className;
    };
    spl_autoload_register($autoloader);

    try {
        dataConverter()->toJson('O:12:"NotLoadedYet":0:{}');
    } finally {
        spl_autoload_unregister($autoloader);
    }

    expect($requested)->toBe([]);
});

it('converts a class PHP could not build even if it were allowed', function () {
    $payload = 'O:'.strlen(AbstractLedger::class).':"'.AbstractLedger::class.'":1:{s:7:"entries";i:3;}';

    expect(dataConverter()->toJson($payload))->toBe('{"entries":3}');
});

it('includes private and protected properties under their plain names', function () {
    expect(dataConverter()->toJson(serialize(new Money(5, 'USD'))))->toBe('{"amount":5,"currency":"USD"}');
});

it('qualifies colliding private properties with the class the payload names', function () {
    $decoded = json_decode(dataConverter()->toJson(serialize(new SavingsAccount)), associative: true);
    $account = new SavingsAccount;

    expect($decoded)->toBe([
        Account::class.'::balance' => $account->inheritedBalance(),
        SavingsAccount::class.'::balance' => $account->ownBalance(),
        'rate' => $account->rate,
    ]);
});

it('hands toArray() the data as a stdClass, never an incomplete object', function () {
    $user = new stdClass;
    $user->name = 'Ada';

    expect(dataConverter()->toArray('a:1:{i:0;O:4:"User":1:{s:4:"name";s:3:"Ada";}}'))->toEqual([$user]);
});

it('still builds an allowed class, then hands its data back', function () {
    $value = Serialized::make()->allowClasses([Money::class])->objectsAsData()->toArray(serialize(new Money(5, 'USD')));

    expect($value)->toBeInstanceOf(stdClass::class)
        ->and((array) $value)->toBe(['amount' => 5, 'currency' => 'USD']);
});

it('reports an object payload as valid', function () {
    expect(Serialized::make()->objectsAsData()->isValid('O:4:"User":0:{}'))->toBeTrue();
});

it('still rejects what it cannot read as data', function (string $payload) {
    expect(fn () => dataConverter()->toJson($payload))->toThrow(UnsafeSerializedDataException::class);
})->with([
    'custom-serialized object' => 'C:11:"ArrayObject":0:{}',
    'enum case' => 'E:11:"Suit:Hearts";',
]);

it('still requires a class named inside an allowed custom body to be allowed', function () {
    $body = 'O:4:"User":0:{}';
    $payload = sprintf('C:%d:"%s":%d:{%s}', strlen(LegacyBox::class), LegacyBox::class, strlen($body), $body);

    $diagnostic = diagnosticFor(
        fn () => Serialized::make()->allowClasses([LegacyBox::class])->objectsAsData()->toJson($payload),
    );

    expect($diagnostic->code)->toBe(DiagnosticCode::DisallowedClass)
        ->and($diagnostic->context['className'])->toBe('User');
});

it('refuses a property named after the marker PHP keeps the class name in', function () {
    $payload = 'O:4:"User":1:{s:27:"__PHP_Incomplete_Class_Name";s:5:"Admin";}';

    $diagnostic = diagnosticFor(fn () => dataConverter()->toJson($payload));

    expect($diagnostic->code)->toBe(DiagnosticCode::ReservedPropertyName)
        ->and($diagnostic->offset)->toBe(20);
});

it('refuses an object that contains itself rather than recursing without end', function () {
    expect(fn () => dataConverter()->toJson('O:4:"User":1:{s:4:"self";r:1;}'))
        ->toThrow(UnrepresentableValueException::class);
});

it('writes out an object that appears twice side by side', function () {
    $payload = 'a:2:{i:0;O:4:"User":1:{s:1:"a";i:1;}i:1;r:2;}';

    expect(dataConverter()->toJson($payload))->toBe('[{"a":1},{"a":1}]');
});
