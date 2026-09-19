<?php

declare(strict_types=1);

namespace Serialized;

/**
 * A property's storage key, split into the class that owns it and its plain name.
 *
 * PHP stores a private property under "\0Declaring\0name" and a protected one under
 * "\0*\0name". The rule for reading that spelling lives here alone: the normalizer asks
 * for the split, the policy asks whether the split leaves a name it can use.
 */
final readonly class PropertyName
{
    private function __construct(
        public string $owner,
        public string $name,
    ) {}

    /**
     * Splits a storage key, falling back to the object's own class for an unmangled one.
     */
    public static function fromStorageKey(string $storageKey, string $objectClass): self
    {
        if (preg_match('/^\x00(?<owner>[^\x00]+)\x00(?<name>.*)$/s', $storageKey, $matches) !== 1) {
            return new self($objectClass, $storageKey);
        }

        $owner = $matches['owner'];

        return new self($owner === '*' ? $objectClass : $owner, $matches['name']);
    }

    /**
     * Tells whether the demangled name is one a stdClass can carry to JSON.
     *
     * A name still holding a NUL cannot be assigned as a property, and assembling the
     * object from an array instead only moves the failure: `json_encode()` drops such a
     * property silently, which is the loss this whole stage exists to prevent.
     */
    public function isRepresentable(): bool
    {
        return ! str_contains($this->name, "\x00");
    }
}
