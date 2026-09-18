<?php

declare(strict_types=1);

namespace Serialized\Parser;

use Serialized\Exceptions\InvalidSerializedDataException;
use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\TokenType;

/**
 * Checks that a token stream forms exactly one complete value, and measures its shape.
 *
 * Nesting is tracked with an explicit stack rather than recursion, so a payload
 * crafted to be thousands of levels deep is rejected instead of exhausting the stack.
 */
final class Parser
{
    /**
     * Validates the token stream and reports the payload's shape.
     *
     * @param  list<Token>  $tokens
     *
     * @throws InvalidSerializedDataException when the stream is not one complete value
     */
    public function parse(string $payload, array $tokens): ParsedPayload
    {
        /** @var list<ArrayFrame> $openArrays */
        $openArrays = [];
        $deepestLevel = 0;
        $elementCount = 0;
        $rootCompleted = false;
        $classNames = [];
        $hasReferences = false;

        foreach ($tokens as $token) {
            if ($token->type === TokenType::Close) {
                $this->closeArray($payload, $openArrays, $token);
                $rootCompleted = $openArrays === [];

                continue;
            }

            $this->fillSlot($payload, $openArrays, $token, $rootCompleted);

            if ($token->className !== null) {
                $classNames[] = ['className' => $token->className, 'offset' => $token->offset];
            }

            $hasReferences = $hasReferences || $token->type->isReference();

            if ($token->type->opensStructure()) {
                $openArrays[] = new ArrayFrame($token->offset, $token->declaredCount ?? 0);
            } else {
                $rootCompleted = $openArrays === [];
            }

            $elementCount++;
            $deepestLevel = max($deepestLevel, count($openArrays) + 1);
        }

        $unclosed = end($openArrays);

        if ($unclosed !== false) {
            throw InvalidSerializedDataException::unclosedArray($payload, $unclosed->offset);
        }

        return new ParsedPayload(
            depth: $deepestLevel,
            elementCount: $elementCount,
            classNames: $classNames,
            hasReferences: $hasReferences,
        );
    }

    /**
     * Accounts for a value or key token against the array that should contain it.
     *
     * @param  list<ArrayFrame>  $openArrays
     */
    private function fillSlot(string $payload, array $openArrays, Token $token, bool $rootCompleted): void
    {
        $currentArray = end($openArrays);

        if ($currentArray === false) {
            if ($rootCompleted) {
                throw InvalidSerializedDataException::trailingBytes($payload, $token->offset);
            }

            return;
        }

        if ($currentArray->isFull()) {
            throw InvalidSerializedDataException::elementCountMismatch(
                $payload,
                $currentArray->offset,
                $currentArray->declaredCount,
                $currentArray->filledPairs() + 1,
            );
        }

        if ($currentArray->expectsKey() && ! $token->type->isValidArrayKey()) {
            throw InvalidSerializedDataException::nonScalarArrayKey($payload, $token->offset, $token->type);
        }

        $currentArray->fillSlot();
    }

    /**
     * Closes the innermost open array, rejecting a brace that closes nothing.
     *
     * @param  list<ArrayFrame>  $openArrays
     */
    private function closeArray(string $payload, array &$openArrays, Token $token): void
    {
        $currentArray = array_pop($openArrays);

        if ($currentArray === null) {
            throw InvalidSerializedDataException::unbalancedClose($payload, $token->offset);
        }

        if (! $currentArray->isFull()) {
            throw InvalidSerializedDataException::elementCountMismatch(
                $payload,
                $currentArray->offset,
                $currentArray->declaredCount,
                $currentArray->filledPairs(),
            );
        }
    }
}
