<?php

declare(strict_types=1);

namespace Serialized\Policy;

use Serialized\Exceptions\LimitExceededException;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Options;
use Serialized\Parser\ParsedPayload;
use Serialized\PropertyName;
use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\TokenType;

/**
 * Decides whether a payload the parser accepted is safe to hand to unserialize().
 *
 * Runs on the parser's metadata, before any value is allocated, so a payload that
 * would build an unwanted object is refused rather than built and discarded.
 */
final readonly class PayloadPolicy
{
    /**
     * Builds the policy, taking the representability check as a collaborator.
     */
    public function __construct(
        private JsonRepresentability $representability = new JsonRepresentability,
        private ClassRestorability $restorability = new ClassRestorability,
    ) {}

    /**
     * Rejects a payload that is too large to be worth reading.
     *
     * Runs before the tokenizer, so an oversized payload costs one strlen() rather
     * than a pass over every byte of it.
     *
     * @throws LimitExceededException when the payload is longer than maxBytes
     */
    public function enforceByteLimit(string $payload, Options $options): void
    {
        $actualBytes = strlen($payload);

        if ($actualBytes > $options->maxBytes) {
            throw LimitExceededException::bytes($actualBytes, $options->maxBytes);
        }
    }

    /**
     * Rejects anything the options do not permit.
     *
     * A nested body is charged the depth and elements the payload around it already
     * spent, so a limit cannot be split across the nesting and stay under itself at
     * every level.
     *
     * @param  list<Token>  $tokens
     * @param  int  $depthSpent  nesting levels used by the value this one sits inside
     * @param  int  $elementsSpent  values already counted outside this one
     *
     * @throws UnsafeSerializedDataException when the payload names a disallowed class or uses references
     * @throws LimitExceededException when the payload is deeper or larger than allowed
     * @throws UnrepresentableValueException when a value has no JSON equivalent
     */
    public function enforce(
        string $payload,
        array $tokens,
        ParsedPayload $parsed,
        Options $options,
        int $depthSpent = 0,
        int $elementsSpent = 0,
    ): void {
        $combinedDepth = $depthSpent + $parsed->depth;

        if ($combinedDepth > $options->maxDepth) {
            throw LimitExceededException::depth($payload, $combinedDepth, $options->maxDepth);
        }

        $allowList = new ClassAllowList($options->allowedClasses);

        foreach ($parsed->classNames as $named) {
            if (! $allowList->allows($named['className'])) {
                if ($this->readsAsData($named['type'], $options)) {
                    continue;
                }

                throw UnsafeSerializedDataException::disallowedClass($payload, $named['offset'], $named['className']);
            }

            if (! $this->restorability->isLoadable($named['className'])) {
                throw UnsafeSerializedDataException::unloadableClass($payload, $named['offset'], $named['className']);
            }

            if (! $this->restorability->canRestore($named['className'], $named['type'])) {
                throw UnsafeSerializedDataException::unrestorableClass($payload, $named['offset'], $named['className']);
            }
        }

        if ($options->objectsAsData) {
            $this->rejectReservedPropertyNames($payload, $parsed->propertyNames);
        }

        $this->representability->enforcePropertyNames($payload, $parsed->propertyNames);
        $this->representability->enforce($payload, $tokens);
    }

    /**
     * Tells whether an object of a class not allowed is read as its properties instead.
     *
     * Only a plain `O:` object qualifies: PHP restores it as an incomplete object holding
     * every property, without loading the class. A `C:` body is the class's own format and
     * comes back empty, and an enum is looked up whatever allowed_classes says.
     */
    private function readsAsData(TokenType $type, Options $options): bool
    {
        return $options->objectsAsData && $type === TokenType::Object;
    }

    /**
     * Rejects a property whose name PHP would write over an incomplete object's class name.
     *
     * @param  list<Token>  $propertyNames
     *
     * @throws UnrepresentableValueException when a property is named after the marker
     */
    private function rejectReservedPropertyNames(string $payload, array $propertyNames): void
    {
        foreach ($propertyNames as $token) {
            if (PropertyName::isIncompleteClassMarker($token->literal())) {
                throw UnrepresentableValueException::reservedPropertyName(
                    $payload,
                    $token->literalOffset ?? $token->offset,
                );
            }
        }
    }
}
