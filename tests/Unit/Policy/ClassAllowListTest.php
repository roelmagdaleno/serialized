<?php

declare(strict_types=1);

use Serialized\Policy\ClassAllowList;

it('allows nothing when the list is empty', function () {
    expect(new ClassAllowList([])->allows('stdClass'))->toBeFalse();
});

it('allows only the classes it was given', function () {
    $allowList = new ClassAllowList([stdClass::class]);

    expect($allowList->allows('stdClass'))->toBeTrue()
        ->and($allowList->allows('DateTime'))->toBeFalse();
});

it('matches however the class name is spelled', function (string $spelling) {
    expect(new ClassAllowList([stdClass::class])->allows($spelling))->toBeTrue();
})->with(['stdClass', 'stdclass', 'STDCLASS', '\stdClass']);

it('matches a namespaced class regardless of a leading separator', function () {
    expect(new ClassAllowList(['\App\Models\Money'])->allows('App\Models\Money'))->toBeTrue();
});
