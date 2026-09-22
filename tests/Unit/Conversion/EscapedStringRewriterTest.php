<?php

declare(strict_types=1);

use Serialized\Conversion\EscapedStringRewriter;
use Tests\Support\LegacyBox;

it('writes an escaped string as the plain string it spells', function (string $payload, string $rewritten) {
    expect(new EscapedStringRewriter()->rewrite($payload))->toBe($rewritten);
})->with([
    'one escape' => ['S:5:"\\68ello";', 's:5:"hello";'],
    'no escape at all' => ['S:5:"hello";', 's:5:"hello";'],
    'empty value' => ['S:0:"";', 's:0:"";'],
    'a NUL and a quote' => ['S:3:"\\00\\22;";', "s:3:\"\0\";\";"],
    'key and value in an array' => ['a:1:{S:3:"\\61bc";S:1:"x";}', 'a:1:{s:3:"abc";s:1:"x";}'],
    'beside other tokens' => ['a:2:{i:0;S:1:"\\7a";i:1;b:1;}', 'a:2:{i:0;s:1:"z";i:1;b:1;}'],
]);

it('hands back a payload without escaped strings byte for byte', function (string $payload) {
    expect(new EscapedStringRewriter()->rewrite($payload))->toBe($payload);
})->with([
    'no S: anywhere' => ['a:1:{s:1:"a";i:1;}'],
    'S: only inside a plain string' => ['s:4:"S:1:";'],
]);

it('rewrites an escaped string inside a custom body and recounts the body', function () {
    $className = LegacyBox::class;
    $payload = sprintf('C:%d:"%s":10:{S:1:"\\78";}', strlen($className), $className);

    expect(new EscapedStringRewriter()->rewrite($payload))
        ->toBe(sprintf('C:%d:"%s":8:{s:1:"x";}', strlen($className), $className));
});
