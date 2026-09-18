<?php

declare(strict_types=1);

namespace Serialized\Policy;

/**
 * The single place that decides whether a class may be unserialized.
 *
 * Comparison is case-insensitive because PHP class names are, so an allow-list entry
 * cannot be slipped past by changing the casing in the payload.
 */
final readonly class ClassAllowList
{
    /**
     * @var list<string>
     */
    private array $normalizedClassNames;

    /**
     * @param  list<class-string>|list<string>  $allowedClasses  names as the caller spells them
     */
    public function __construct(array $allowedClasses)
    {
        $this->normalizedClassNames = array_values(array_map($this->normalize(...), $allowedClasses));
    }

    /**
     * Tells whether the payload may instantiate the given class.
     */
    public function allows(string $className): bool
    {
        return in_array($this->normalize($className), $this->normalizedClassNames, strict: true);
    }

    /**
     * Strips a leading separator and lowercases, giving one spelling per class.
     */
    private function normalize(string $className): string
    {
        return strtolower(ltrim($className, '\\'));
    }
}
