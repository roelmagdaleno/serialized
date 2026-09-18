<?php

declare(strict_types=1);

use Serialized\Options;
use Serialized\Serialized;

it('ships the documented defaults', function () {
    $options = new Options;

    expect($options->maxBytes)->toBe(16 * 1024 * 1024)
        ->and($options->maxDepth)->toBe(64)
        ->and($options->maxElements)->toBe(1_000_000)
        ->and($options->allowedClasses)->toBe([])
        ->and($options->jsonFlags)->toBe(
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
});

it('drops pretty printing when asked for compact output', function () {
    $converter = Serialized::make()->compact();

    expect($converter->toJson(chromePayload()))->not->toContain("\n")
        ->and($converter->toJson('a:1:{s:1:"a";i:1;}'))->toBe('{"a":1}');
});

it('restores pretty printing', function () {
    expect(Serialized::make()->compact()->pretty()->toJson('a:1:{s:1:"a";i:1;}'))->toBe(<<<'JSON'
    {
        "a": 1
    }
    JSON);
});

it('adds requested flags on top of the defaults', function () {
    $converter = Serialized::make()->withJsonFlags(JSON_FORCE_OBJECT);

    expect($converter->toJson('a:1:{i:0;i:1;}'))->toContain('"0": 1')
        ->and($converter->options()->jsonFlags & JSON_UNESCAPED_SLASHES)->toBe(JSON_UNESCAPED_SLASHES);
});

it('cannot be configured to stop throwing on an encoding error', function () {
    $converter = Serialized::make()->withJsonFlags(0);

    expect($converter->options()->jsonFlags & JSON_THROW_ON_ERROR)->toBe(JSON_THROW_ON_ERROR);
});

it('returns a new instance from every output option', function () {
    $original = Serialized::make();

    expect($original->compact())->not->toBe($original)
        ->and($original->pretty())->not->toBe($original)
        ->and($original->withJsonFlags(JSON_FORCE_OBJECT))->not->toBe($original)
        ->and($original->options()->jsonFlags)->toBe(new Options()->jsonFlags);
});
