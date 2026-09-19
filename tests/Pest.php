<?php

declare(strict_types=1);

use Serialized\Diagnostics\Diagnostic;
use Serialized\Exceptions\SerializedException;

/**
 * Tells whether PHP's own unserialize() also rejects a payload.
 *
 * Every rejection test asserts this, so the tokenizer can never end up more
 * permissive than the function it is guarding.
 */
function phpRejects(string $payload): bool
{
    $raisedWarning = false;

    set_error_handler(static function () use (&$raisedWarning): bool {
        $raisedWarning = true;

        return true;
    });

    try {
        $value = unserialize($payload, ['allowed_classes' => false]);
    } finally {
        restore_error_handler();
    }

    return $raisedWarning || ($value === false && $payload !== 'b:0;');
}

/**
 * Runs an action expected to be rejected and returns the diagnostic it threw.
 *
 * Keeps every rejection test to a single shape: act, then assert on offset,
 * reason and fix rather than on the rendered message.
 */
function diagnosticFor(callable $action): Diagnostic
{
    try {
        $action();
    } catch (SerializedException $exception) {
        return $exception->diagnostic() ?? throw new RuntimeException('The exception carried no diagnostic.');
    }

    throw new RuntimeException('Expected the action to be rejected, but it succeeded.');
}

/**
 * The example payload from the spec: a WordPress browser-version array.
 */
function chromePayload(): string
{
    return 'a:10:{s:4:"name";s:6:"Chrome";s:7:"version";s:9:"103.0.0.0";s:8:"platform";s:7:"Windows";'
        .'s:10:"update_url";s:29:"https://www.google.com/chrome";s:7:"img_src";'
        .'s:44:"https://s.w.org/images/browsers/chrome.png?1";s:11:"img_src_ssl";'
        .'s:44:"https://s.w.org/images/browsers/chrome.png?1";s:15:"current_version";s:2:"18";'
        .'s:7:"upgrade";b:0;s:8:"insecure";b:0;s:6:"mobile";b:0;}';
}

/**
 * Builds the payload for an empty object of a class, with the length PHP would write.
 */
function objectPayloadFor(string $className): string
{
    return sprintf('O:%d:"%s":0:{}', strlen($className), $className);
}

/**
 * Reads a stored real-world payload from the fixtures directory.
 */
function storedPayload(string $name): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/{$name}.txt");
}
