<?php

declare(strict_types=1);

namespace Serialized;

use Serialized\Conversion\JsonEncoder;
use Serialized\Conversion\SafeUnserializer;
use Serialized\Conversion\ValueNormalizer;
use Serialized\Exceptions\SerializedException;
use Serialized\Parser\ParsedPayload;
use Serialized\Parser\Parser;
use Serialized\Policy\ClassAllowList;
use Serialized\Policy\PayloadPolicy;
use Serialized\Tokenizer\Tokenizer;
use Serialized\Tokenizer\TokenType;
use Throwable;

/**
 * Runs a payload through the pipeline under a given set of options.
 *
 * Immutable: every with* method returns a new instance, so a configured converter is
 * safe to share between requests, cache, or bind once in a service container.
 */
final readonly class SerializedConverter
{
    /**
     * Builds a converter, taking the pipeline stages as collaborators for testing.
     */
    public function __construct(
        private Options $options = new Options,
        private Tokenizer $tokenizer = new Tokenizer,
        private Parser $parser = new Parser,
        private PayloadPolicy $policy = new PayloadPolicy,
        private SafeUnserializer $unserializer = new SafeUnserializer,
        private ValueNormalizer $normalizer = new ValueNormalizer,
        private JsonEncoder $encoder = new JsonEncoder,
    ) {}

    /**
     * Converts a serialized payload into pretty-printed JSON.
     *
     * @throws SerializedException when the payload is malformed, unsafe or unrepresentable
     */
    public function toJson(string $payload): string
    {
        $value = $this->normalizer->normalize($this->toArray($payload));

        return $this->encoder->encode($value, $this->options);
    }

    /**
     * Converts a payload to JSON, or returns null if it cannot be converted.
     *
     * Catches Throwable rather than just this package's exceptions: a method whose
     * contract is "never throws" must also absorb what PHP itself might raise.
     */
    public function tryToJson(string $payload): ?string
    {
        try {
            return $this->toJson($payload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Unserializes a payload into its PHP value, stopping short of JSON encoding.
     *
     * @throws SerializedException when the payload is malformed or unsafe
     */
    public function toArray(string $payload): mixed
    {
        $this->validate($payload);

        return $this->unserializer->unserialize(
            $payload,
            new ClassAllowList($this->options->allowedClasses)->normalizedClassNames(),
        );
    }

    /**
     * Tells whether a payload is well formed and safe to convert.
     */
    public function isValid(string $payload): bool
    {
        try {
            $this->validate($payload);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns a converter that prints JSON across multiple indented lines.
     */
    public function pretty(): self
    {
        return $this->withOptions(jsonFlags: $this->options->jsonFlags | JSON_PRETTY_PRINT);
    }

    /**
     * Returns a converter that prints JSON on a single line.
     */
    public function compact(): self
    {
        return $this->withOptions(jsonFlags: $this->options->jsonFlags & ~JSON_PRETTY_PRINT);
    }

    /**
     * Returns a converter with extra json_encode flags added to the defaults.
     *
     * Additive on purpose: passing flags should not silently drop the unescaped
     * slashes and unicode that make the output readable.
     */
    public function withJsonFlags(int $jsonFlags): self
    {
        return $this->withOptions(jsonFlags: $this->options->jsonFlags | $jsonFlags);
    }

    /**
     * Returns the options this converter runs under.
     */
    public function options(): Options
    {
        return $this->options;
    }

    /**
     * Returns a converter that may unserialize objects of the given classes.
     *
     * Allowing a class is a statement of trust: unserializing it can run its
     * __wakeup() and __destruct() on data the payload controls.
     *
     * @param  list<class-string>  $allowedClasses
     */
    public function allowClasses(array $allowedClasses): self
    {
        return $this->withOptions(allowedClasses: $allowedClasses);
    }

    /**
     * Returns a converter that rejects payloads longer than the given byte count.
     */
    public function withMaxBytes(int $maxBytes): self
    {
        return $this->withOptions(maxBytes: $maxBytes);
    }

    /**
     * Returns a converter that rejects payloads nested deeper than the given level.
     */
    public function withMaxDepth(int $maxDepth): self
    {
        return $this->withOptions(maxDepth: $maxDepth);
    }

    /**
     * Returns a converter that rejects payloads holding more than the given number of values.
     */
    public function withMaxElements(int $maxElements): self
    {
        return $this->withOptions(maxElements: $maxElements);
    }

    /**
     * Runs the validation stages and reports what they found.
     *
     * @throws SerializedException when any stage rejects the payload
     */
    private function validate(string $payload): ParsedPayload
    {
        $this->policy->enforceByteLimit($payload, $this->options);

        return $this->validateValue($payload, 0, strlen($payload), depthSpent: 0, elementsSpent: 0);
    }

    /**
     * Validates the one value filling a byte range, then every body nested inside it.
     *
     * A custom-serialized body is itself a serialized payload that unserialize() hands
     * to the class, so it goes through the same three stages rather than being trusted
     * for being opaque.
     *
     * @throws SerializedException when any stage rejects the value
     */
    private function validateValue(
        string $payload,
        int $from,
        int $through,
        int $depthSpent,
        int $elementsSpent,
    ): ParsedPayload {
        $tokens = $this->tokenizer->tokenize(
            $payload,
            $from,
            $through,
            maxElements: $this->options->maxElements - $elementsSpent,
        );
        $parsed = $this->parser->parse($payload, $tokens);

        $this->policy->enforce($payload, $tokens, $parsed, $this->options, $depthSpent, $elementsSpent);

        foreach ($tokens as $token) {
            if ($token->type !== TokenType::CustomObject) {
                continue;
            }

            $bodyStart = $token->literalOffset ?? $token->offset;

            $elementsSpent += $this->validateValue(
                $payload,
                $bodyStart,
                $bodyStart + ($token->literalLength ?? 0),
                depthSpent: $depthSpent + $parsed->depth,
                elementsSpent: $elementsSpent + $parsed->elementCount,
            )->elementCount;
        }

        return $parsed;
    }

    /**
     * Clones the converter with some options replaced.
     *
     * @param  list<class-string>|null  $allowedClasses
     */
    private function withOptions(
        ?int $maxBytes = null,
        ?int $maxDepth = null,
        ?int $maxElements = null,
        ?array $allowedClasses = null,
        ?int $jsonFlags = null,
    ): self {
        return new self(
            new Options(
                maxBytes: $maxBytes ?? $this->options->maxBytes,
                maxDepth: $maxDepth ?? $this->options->maxDepth,
                maxElements: $maxElements ?? $this->options->maxElements,
                allowedClasses: $allowedClasses ?? $this->options->allowedClasses,
                jsonFlags: $jsonFlags ?? $this->options->jsonFlags,
            ),
            $this->tokenizer,
            $this->parser,
            $this->policy,
            $this->unserializer,
            $this->normalizer,
            $this->encoder,
        );
    }
}
