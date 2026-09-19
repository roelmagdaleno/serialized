<?php

declare(strict_types=1);

namespace Serialized\Policy;

use BackedEnum;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\TokenType;

/**
 * Rejects values that are valid in PHP but have no JSON equivalent.
 *
 * Checked on the token stream rather than after unserializing, so the exception can
 * name the byte at fault — and so `json_encode()` is never the thing that fails.
 */
final class JsonRepresentability
{
    private const array NON_FINITE_FLOATS = ['NAN', 'INF', '-INF'];

    /**
     * Rejects the first value JSON cannot carry.
     *
     * @param  list<Token>  $tokens
     *
     * @throws UnrepresentableValueException when a value has no JSON equivalent
     */
    public function enforce(string $payload, array $tokens): void
    {
        foreach ($tokens as $token) {
            $this->enforceToken($payload, $token);
        }
    }

    /**
     * Rejects one token if its literal cannot be expressed in JSON.
     */
    private function enforceToken(string $payload, Token $token): void
    {
        if ($token->type === TokenType::String && ! mb_check_encoding($token->literal, 'UTF-8')) {
            throw UnrepresentableValueException::nonUtf8String($payload, $token->literalOffset ?? $token->offset);
        }

        if ($token->type === TokenType::Float && in_array($token->literal, self::NON_FINITE_FLOATS, strict: true)) {
            throw UnrepresentableValueException::nonFiniteFloat(
                $payload,
                $token->literalOffset ?? $token->offset,
                $token->literal,
            );
        }

        // The class is known to be loaded: the policy rejects an allow-listed class it cannot load.
        if ($token->type === TokenType::Enum && ! is_a((string) $token->className, BackedEnum::class, allow_string: true)) {
            throw UnrepresentableValueException::nonBackedEnum(
                $payload,
                $token->literalOffset ?? $token->offset,
                $token->literal,
            );
        }
    }
}
