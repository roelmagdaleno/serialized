<?php

declare(strict_types=1);

use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Serialized;
use Tests\Support\AbstractLedger;
use Tests\Support\Account;
use Tests\Support\Direction;
use Tests\Support\Money;
use Tests\Support\SavingsAccount;
use Tests\Support\Suit;

it('includes private and protected properties in the JSON', function () {
    $json = Serialized::make()
        ->allowClasses([Money::class])
        ->compact()
        ->toJson(serialize(new Money(5, 'USD')));

    expect($json)->toBe('{"amount":5,"currency":"USD"}');
});

it('qualifies property names that collide across the class hierarchy', function () {
    $json = Serialized::make()
        ->allowClasses([SavingsAccount::class, Account::class])
        ->compact()
        ->toJson(serialize(new SavingsAccount));

    $decoded = json_decode($json, associative: true);
    $account = new SavingsAccount;

    expect($decoded)->toBe([
        Account::class.'::balance' => $account->inheritedBalance(),
        SavingsAccount::class.'::balance' => $account->ownBalance(),
        'rate' => $account->rate,
    ]);
});

it('keeps an object with only numeric property names a JSON object', function () {
    $json = Serialized::make()
        ->allowClasses([stdClass::class])
        ->compact()
        ->toJson('O:8:"stdClass":1:{s:1:"0";s:4:"zero";}');

    expect($json)->toBe('{"0":"zero"}');
});

it('still converts an empty object to an empty JSON object', function () {
    expect(Serialized::make()->allowClasses([stdClass::class])->toJson('O:8:"stdClass":0:{}'))
        ->toBe('{}');
});

it('normalizes an object nested inside an array', function () {
    $json = Serialized::make()
        ->allowClasses([Money::class])
        ->compact()
        ->toJson(serialize([new Money(7, 'EUR')]));

    expect($json)->toBe('[{"amount":7,"currency":"EUR"}]');
});

it('normalizes an object nested inside another object', function () {
    $outer = new stdClass;
    $outer->price = new Money(9, 'GBP');

    $json = Serialized::make()
        ->allowClasses([stdClass::class, Money::class])
        ->compact()
        ->toJson(serialize($outer));

    expect($json)->toBe('{"price":{"amount":9,"currency":"GBP"}}');
});

it('renders a backed enum as its value', function () {
    $json = Serialized::make()->allowClasses([Suit::class])->toJson(serialize(Suit::Hearts));

    expect($json)->toBe('"H"');
});

it('rejects a non-backed enum with a byte offset instead of failing to encode', function () {
    $payload = serialize(Direction::North);
    $converter = Serialized::make()->allowClasses([Direction::class]);

    expect(fn () => $converter->toJson($payload))->toThrow(UnrepresentableValueException::class);

    $diagnostic = diagnosticFor(fn () => $converter->toJson($payload));

    expect($diagnostic->offset)->toBe(strpos($payload, 'Tests'))
        ->and($diagnostic->reason)->toContain('enum')
        ->and($diagnostic->fix)->not->toBeEmpty();
});

it('rejects an allow-listed name that is not a loadable class', function () {
    $payload = 'O:9:"Countable":0:{}';
    $converter = Serialized::make()->allowClasses([Countable::class]);

    expect(fn () => $converter->toJson($payload))->toThrow(UnsafeSerializedDataException::class);

    $diagnostic = diagnosticFor(fn () => $converter->toJson($payload));

    expect($diagnostic->offset)->toBe(0)
        ->and($diagnostic->reason)->toContain('Countable')
        ->and($diagnostic->fix)->not->toBeEmpty();
});

it('rejects an allow-listed abstract class', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::make()
        ->allowClasses([AbstractLedger::class])
        ->toJson(objectPayloadFor(AbstractLedger::class)));

    expect($diagnostic->reason)->toContain(AbstractLedger::class)
        ->and($diagnostic->fix)->not->toBeEmpty();
});

it('rejects an enum written as a plain object', function () {
    $converter = Serialized::make()->allowClasses([Suit::class]);
    $payload = objectPayloadFor(Suit::class);

    expect(fn () => $converter->toJson($payload))->toThrow(UnsafeSerializedDataException::class);

    expect(diagnosticFor(fn () => $converter->toJson($payload))->reason)->toContain(Suit::class);
});

it('leaves toArray returning the real object, unnormalized', function () {
    $original = new Money(5, 'USD');

    $value = Serialized::make()->allowClasses([Money::class])->toArray(serialize($original));

    expect($value)->toBeInstanceOf(Money::class)
        ->and($value)->toEqual($original)
        ->and($original->describe())->toBe('5 USD');
});
