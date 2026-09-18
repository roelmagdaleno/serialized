<?php

declare(strict_types=1);

namespace Serialized\Conversion;

use JsonException;
use Serialized\Exceptions\JsonEncodingException;
use Serialized\Options;

/**
 * The only place in the package that calls json_encode().
 */
final class JsonEncoder
{
    /**
     * Encodes an unserialized value as JSON using the configured flags.
     *
     * JSON_THROW_ON_ERROR is forced on: without it `json_encode()` signals failure by
     * returning `false`, which would hand the caller a value instead of an exception.
     *
     * @throws JsonEncodingException when `json_encode()` rejects the value
     */
    public function encode(mixed $value, Options $options): string
    {
        try {
            return json_encode($value, $options->jsonFlags | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw JsonEncodingException::encodingFailed($exception);
        }
    }
}
