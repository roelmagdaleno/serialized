<?php

declare(strict_types=1);

namespace Serialized;

/**
 * The limits, allow-list and JSON flags a conversion runs under.
 */
final readonly class Options
{
    public const int DEFAULT_MAX_BYTES = 16 * 1024 * 1024;

    public const int DEFAULT_MAX_DEPTH = 64;

    public const int DEFAULT_MAX_ELEMENTS = 1_000_000;

    public const int DEFAULT_JSON_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR;

    /**
     * @param  list<class-string>  $allowedClasses  classes the caller trusts enough to unserialize
     */
    public function __construct(
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
        public int $maxDepth = self::DEFAULT_MAX_DEPTH,
        public int $maxElements = self::DEFAULT_MAX_ELEMENTS,
        public array $allowedClasses = [],
        public int $jsonFlags = self::DEFAULT_JSON_FLAGS,
    ) {}
}
