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
     * Unserializes a payload the tokenizer, parser and policy have already accepted.
     *
     * @param  list<class-string>  $allowedClasses
     *
     * @throws InvalidSerializedDataException when PHP itself rejects the payload
     */
    public function unserialize(string $payload, array $allowedClasses): mixed
    {
        $failure = null;

        set_error_handler(static function (int $level, string $message) use (&$failure): bool {
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
