<?php

declare(strict_types=1);

namespace Serialized;

use Serialized\Exceptions\SerializedException;

/**
 * The package's entry point: the common cases as one-liners.
 *
 * Every method delegates to a default SerializedConverter. Reach for make() when the
 * conversion needs configuring.
 */
final class Serialized
{
    /**
     * Converts a serialized payload into pretty-printed JSON.
     *
     * @throws SerializedException when the payload is malformed, unsafe or unrepresentable
     */
    public static function toJson(string $payload): string
    {
        return self::make()->toJson($payload);
    }

    /**
     * Converts a payload to JSON, or returns null if it cannot be converted.
     */
    public static function tryToJson(string $payload): ?string
    {
        return self::make()->tryToJson($payload);
    }

    /**
     * Unserializes a payload into its PHP value, stopping short of JSON encoding.
     *
     * @throws SerializedException when the payload is malformed or unsafe
     */
    public static function toArray(string $payload): mixed
    {
        return self::make()->toArray($payload);
    }

    /**
     * Tells whether a payload is well formed and safe to convert.
     */
    public static function isValid(string $payload): bool
    {
        return self::make()->isValid($payload);
    }

    /**
     * Returns a converter with the default options, ready to be configured.
     */
    public static function make(): SerializedConverter
    {
        return new SerializedConverter;
    }
}
