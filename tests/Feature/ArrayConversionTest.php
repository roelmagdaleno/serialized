<?php

declare(strict_types=1);

use Serialized\Serialized;

it('converts the Chrome payload to the JSON in the spec, byte for byte', function () {
    expect(Serialized::toJson(chromePayload()))->toBe(<<<'JSON'
    {
        "name": "Chrome",
        "version": "103.0.0.0",
        "platform": "Windows",
        "update_url": "https://www.google.com/chrome",
        "img_src": "https://s.w.org/images/browsers/chrome.png?1",
        "img_src_ssl": "https://s.w.org/images/browsers/chrome.png?1",
        "current_version": "18",
        "upgrade": false,
        "insecure": false,
        "mobile": false
    }
    JSON);
});

it('converts a list to a JSON array and a map to a JSON object', function () {
    expect(Serialized::toJson(serialize(['a', 'b'])))->toBe(<<<'JSON'
    [
        "a",
        "b"
    ]
    JSON)
        ->and(Serialized::toJson(serialize(['k' => 'v'])))->toBe(<<<'JSON'
    {
        "k": "v"
    }
    JSON);
});

it('converts an empty array to an empty JSON array', function () {
    expect(Serialized::toJson('a:0:{}'))->toBe('[]');
});

it('round-trips anything PHP can serialize without objects', function (mixed $value) {
    expect(Serialized::toArray(serialize($value)))->toBe($value);
})->with([
    'nested map' => [['a' => ['b' => ['c' => 1]]]],
    'mixed keys' => [[0 => 'zero', 'one' => 1, 5 => null]],
    'list of lists' => [[[1, 2], [3, 4]]],
    'every scalar' => [[null, true, false, 0, -1, 1.5, '', 'text']],
    'sparse list' => [[3 => 'a', 7 => 'b']],
]);

it('reports a length mismatch inside an array with a byte-accurate offset', function () {
    $diagnostic = diagnosticFor(fn () => Serialized::toJson('a:1:{s:4:"name";s:6:"Chrom";}'));

    expect($diagnostic->offset)->toBe(18)
        ->and($diagnostic->reason)->toContain('declared 6')
        ->and($diagnostic->reason)->toContain('found 5')
        ->and($diagnostic->fix)->toContain('s:5');
});
