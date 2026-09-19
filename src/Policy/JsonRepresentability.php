<?php

declare(strict_types=1);

namespace Serialized\Policy;

use BackedEnum;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\PropertyName;
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
    private const array NON_FINITE_SPELLINGS = ['NAN', 'INF', '-INF'];

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
     * Tells whether a float literal denotes a finite value.
     *
     * Both halves are needed: PHP casts the spelled forms to 0.0 rather than to the values
     * they name, while a literal like 1e999 spells a finite number and overflows to
     * infinity on being read.
     */
    private function isFinite(string $literal): bool
    {
        return ! in_array($literal, self::NON_FINITE_SPELLINGS, strict: true)
            && is_finite((float) $literal);
    }

    /**
     * Rejects the first property name that would not survive being demangled.
     *
     * @param  list<Token>  $propertyNames
     *
     * @throws UnrepresentableValueException when a name still holds a NUL once demangled
     */
    public function enforcePropertyNames(string $payload, array $propertyNames): void
    {
        foreach ($propertyNames as $token) {
            if (PropertyName::fromStorageKey($token->literal(), '')->isRepresentable()) {
                continue;
            }

            throw UnrepresentableValueException::unusablePropertyName(
                $payload,
                $token->literalOffset ?? $token->offset,
            );
        }
    }

    /**
     * Rejects one token if its literal cannot be expressed in JSON.
     */
    private function enforceToken(string $payload, Token $token): void
    {
        if ($token->type === TokenType::String && ! mb_check_encoding($token->literal(), 'UTF-8')) {
            throw UnrepresentableValueException::nonUtf8String($payload, $token->literalOffset ?? $token->offset);
        }

        if ($token->type === TokenType::Float && ! $this->isFinite($token->literal())) {
            throw UnrepresentableValueException::nonFiniteFloat(
                $payload,
                $token->literalOffset ?? $token->offset,
                $token->literal(),
            );
        }

        // The class is known to be loaded: the policy rejects an allow-listed class it cannot load.
        if ($token->type === TokenType::Enum && ! is_a((string) $token->className, BackedEnum::class, allow_string: true)) {
            throw UnrepresentableValueException::nonBackedEnum(
                $payload,
                $token->literalOffset ?? $token->offset,
                $token->literal(),
            );
        }
    }
}
