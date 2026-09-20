<?php

declare(strict_types=1);

use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticCode;
use Serialized\Diagnostics\SnippetRenderer;

function renderSnippet(string $payload, int $offset): string
{
    return new SnippetRenderer()->render(new Diagnostic(
        code: DiagnosticCode::UnbalancedClose,
        payload: $payload,
        offset: $offset,
    ));
}

it('places the caret under the offending byte', function () {
    $snippet = renderSnippet('a:1:{i:0;N;}', 5);

    expect($snippet)->toBe(<<<'TXT'
        a:1:{i:0;N;}
             ^
        TXT);
});

it('places the caret under the first byte', function () {
    expect(renderSnippet('N;', 0))->toBe(<<<'TXT'
        N;
        ^
        TXT);
});

it('places the caret under the last byte', function () {
    expect(renderSnippet('N;', 1))->toBe(<<<'TXT'
        N;
         ^
        TXT);
});

it('places the caret one past the end when the payload is truncated', function () {
    expect(renderSnippet('i:42', 4))->toBe(<<<'TXT'
        i:42
            ^
        TXT);
});

it('truncates a long payload around the offset', function () {
    $payload = 's:200:"'.str_repeat('x', 200).'";';

    [$line, $caretLine] = explode("\n", renderSnippet($payload, 100));
    $caretColumn = strlen($caretLine) - 1;

    expect($line)->toStartWith('...')
        ->and($line)->toEndWith('...')
        ->and(strlen($line))->toBeLessThan(strlen($payload))
        ->and($caretLine)->toEndWith('^')
        ->and($line[$caretColumn])->toBe('x');
});

it('does not truncate a payload that already fits', function () {
    $payload = str_repeat('x', 60);

    expect(renderSnippet($payload, 0))->not->toContain('...');
});

it('renders unprintable bytes as escape sequences so the caret stays aligned', function () {
    [$line, $caretLine] = explode("\n", renderSnippet("s:1:\"\x00\";", 5));
    $caretColumn = strlen($caretLine) - 1;

    expect($line)->toContain('\x00')
        ->and(substr($line, $caretColumn, 4))->toBe('\x00');
});
