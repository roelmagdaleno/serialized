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
        /** @var list<StructureFrame> $openStructures */
        $openStructures = [];
        $deepestLevel = 0;
        $elementCount = 0;
        $rootCompleted = false;
        $classNames = [];
        $referenceOffset = null;
        $propertyNames = [];

        foreach ($tokens as $token) {
            if ($token->type === TokenType::Close) {
                $this->closeStructure($payload, $openStructures, $token);
                $rootCompleted = $openStructures === [];

                continue;
            }

            $currentStructure = $this->innermostFrame($openStructures);

            if ($this->isPropertyNameHoldingNul($currentStructure, $token)) {
                $propertyNames[] = $token;
            }

            $this->fillSlot($payload, $currentStructure, $token, $rootCompleted);

            if ($token->className !== null) {
                $classNames[] = [
                    'className' => $token->className,
                    'offset' => $token->offset,
                    'type' => $token->type,
                ];
            }

            if ($referenceOffset === null && $token->type->isReference()) {
                $referenceOffset = $token->offset;
            }

            if ($token->type->opensStructure()) {
                $openStructures[] = new StructureFrame($token);
            } else {
                $rootCompleted = $openStructures === [];
            }

            $elementCount++;
            $deepestLevel = max($deepestLevel, count($openStructures) + 1);
        }

        $unclosed = $this->innermostFrame($openStructures);

        if ($unclosed !== null) {
            throw InvalidSerializedDataException::unclosedStructure($payload, $unclosed->token);
        }

        return new ParsedPayload(
            depth: $deepestLevel,
            elementCount: $elementCount,
            classNames: $classNames,
            referenceOffset: $referenceOffset,
            propertyNames: $propertyNames,
        );
    }

    /**
     * Accounts for a value or key token against the structure that should contain it.
     *
     * A token with no open structure around it is the root value, unless the root has
     * already been completed — then the payload holds more than the one value it may.
     */
    private function fillSlot(string $payload, ?StructureFrame $currentStructure, Token $token, bool $rootCompleted): void
    {
        if ($currentStructure === null) {
            if ($rootCompleted) {
                throw InvalidSerializedDataException::trailingBytes($payload, $token->offset);
            }

            return;
        }

        if ($currentStructure->isFull()) {
            throw InvalidSerializedDataException::elementCountMismatch(
                $payload,
                $currentStructure->token,
                $currentStructure->filledPairs() + 1,
            );
        }

        if ($currentStructure->expectsKey() && ! $token->type->isValidKey()) {
            throw InvalidSerializedDataException::nonScalarKey($payload, $token, $currentStructure->token);
        }

        $currentStructure->fillSlot();
    }

    /**
     * Tells whether a token names an object property and holds a NUL byte.
     *
     * Structure is what the parser can see and the policy cannot: only a key slot of an
     * object is a property name. Whether such a name is usable is the policy's call, so
     * this narrows rather than decides — a private or protected name holds NULs too.
     */
    private function isPropertyNameHoldingNul(?StructureFrame $currentStructure, Token $token): bool
    {
        return $currentStructure !== null
            && $currentStructure->token->type === TokenType::Object
            && $currentStructure->expectsKey()
            && $token->type === TokenType::String
            && str_contains($token->literal(), "\x00");
    }

    /**
     * Returns the structure a token currently sits inside, or null at the root.
     *
     * @param  list<StructureFrame>  $openStructures
     */
    private function innermostFrame(array $openStructures): ?StructureFrame
    {
        $frame = end($openStructures);

        return $frame === false ? null : $frame;
    }

    /**
     * Closes the innermost open structure, rejecting a brace that closes nothing.
     *
     * @param  list<StructureFrame>  $openStructures
     */
    private function closeStructure(string $payload, array &$openStructures, Token $token): void
    {
        $currentStructure = array_pop($openStructures);

        if ($currentStructure === null) {
            throw InvalidSerializedDataException::unbalancedClose($payload, $token->offset);
        }

        if (! $currentStructure->isFull()) {
            throw InvalidSerializedDataException::elementCountMismatch(
                $payload,
                $currentStructure->token,
                $currentStructure->filledPairs(),
            );
        }
    }
}
