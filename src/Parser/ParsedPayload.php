<?php

declare(strict_types=1);

namespace Serialized\Parser;

/**
 * What the parser learned about a payload, without unserializing it.
 *
 * Metadata only: the policy needs shape and class names to make its decisions, and
 * carrying values here would mean deserializing the payload twice — once unsafely.
 */
final readonly class ParsedPayload
{
    /**
     * @param  int  $depth  deepest nesting level reached, counting the root as one
     * @param  int  $elementCount  total values in the payload, keys included
     * @param  list<array{className: string, offset: int}>  $classNames  every class named by the payload
     * @param  bool  $hasReferences  whether the payload contains an R: or r: back-reference
     */
    public function __construct(
        public int $depth,
        public int $elementCount,
        public array $classNames,
        public bool $hasReferences,
    ) {}
}
