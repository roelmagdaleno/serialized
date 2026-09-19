<?php

declare(strict_types=1);

namespace Serialized\Parser;

use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\TokenType;

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
     * @param  list<array{className: string, offset: int, type: TokenType}>  $classNames  every class named by the payload
     * @param  int|null  $referenceOffset  byte position of the first R: or r: token, null when there is none
     * @param  list<Token>  $propertyNames  object property names holding a NUL, which the policy reads closely
     */
    public function __construct(
        public int $depth,
        public int $elementCount,
        public array $classNames,
        public ?int $referenceOffset,
        public array $propertyNames = [],
    ) {}
}
