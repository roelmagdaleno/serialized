<?php

declare(strict_types=1);

namespace Serialized\Conversion;

use Serialized\Exceptions\InvalidSerializedDataException;

/**
 * The only place in the package that calls unserialize().
 *
 * allowed_classes is always passed explicitly — false when nothing is allowed — so
 * no payload can ever reach PHP's object instantiation without a caller having named
 * the class first.
 */
final class SafeUnserializer
{
    /**
     * The severities that mean unserialize() could not read the payload.
     *
     * Anything quieter is a remark about the classes being restored rather than about the
     * payload: a stored object holding a property its class has since dropped raises a
     * deprecation, and reading that as failure would refuse a payload PHP just rebuilt.
     */
    private const array FAILURE_LEVELS = [E_WARNING, E_USER_WARNING];

    /**
     * Unserializes a payload the tokenizer, parser and policy have already accepted.
     *
     * @param  list<string>  $allowedClasses  normalized by ClassAllowList, never the caller's raw spelling
     *
     * @throws InvalidSerializedDataException when PHP itself rejects the payload
     */
    public function unserialize(string $payload, array $allowedClasses): mixed
    {
        $failure = null;

        set_error_handler(static function (int $level, string $message) use (&$failure): bool {
            if (! in_array($level, self::FAILURE_LEVELS, strict: true)) {
                // Handing it back to PHP keeps the remark visible without it becoming ours.
                return false;
            }

            $failure = $message;

            return true;
        });

        try {
            $value = unserialize($payload, [
                'allowed_classes' => $allowedClasses === [] ? false : $allowedClasses,
            ]);
        } finally {
            restore_error_handler();
        }

        if ($failure !== null) {
            throw InvalidSerializedDataException::rejectedByPhp($payload, $failure);
        }

        return $value;
    }
}
