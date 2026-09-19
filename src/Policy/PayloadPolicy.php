<?php

declare(strict_types=1);

namespace Serialized\Policy;

use Serialized\Exceptions\LimitExceededException;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\Exceptions\UnsafeSerializedDataException;
use Serialized\Options;
use Serialized\Parser\ParsedPayload;
use Serialized\Tokenizer\Token;

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
     * @param  list<Token>  $tokens
     *
     * @throws UnsafeSerializedDataException when the payload names a disallowed class or uses references
     * @throws LimitExceededException when the payload is deeper or larger than allowed
     * @throws UnrepresentableValueException when a value has no JSON equivalent
     */
    public function enforce(string $payload, array $tokens, ParsedPayload $parsed, Options $options): void
    {
        if ($parsed->depth > $options->maxDepth) {
            throw LimitExceededException::depth($payload, $parsed->depth, $options->maxDepth);
        }

        if ($parsed->elementCount > $options->maxElements) {
            throw LimitExceededException::elements($payload, $parsed->elementCount, $options->maxElements);
        }

        $allowList = new ClassAllowList($options->allowedClasses);

        foreach ($parsed->classNames as $named) {
            if (! $allowList->allows($named['className'])) {
                throw UnsafeSerializedDataException::disallowedClass($payload, $named['offset'], $named['className']);
            }

            if (! $this->restorability->isLoadable($named['className'])) {
                throw UnsafeSerializedDataException::unloadableClass($payload, $named['offset'], $named['className']);
            }

            if (! $this->restorability->canRestore($named['className'], $named['type'])) {
                throw UnsafeSerializedDataException::unrestorableClass($payload, $named['offset'], $named['className']);
            }
        }

        if ($parsed->referenceOffset !== null) {
            throw UnsafeSerializedDataException::references($payload, $parsed->referenceOffset);
        }

        $this->representability->enforce($payload, $tokens);
    }
}
