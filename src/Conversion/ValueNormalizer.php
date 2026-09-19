<?php

declare(strict_types=1);

namespace Serialized\Conversion;

use stdClass;
use UnitEnum;

/**
 * Rewrites an unserialized value into the shape json_encode() can carry faithfully.
 *
 * json_encode() reads an object's public properties and silently drops the rest, so an
 * allow-listed value object encodes as `{}`. Every object becomes a stdClass carrying all
 * of its properties under their demangled names instead.
 */
final class ValueNormalizer
{
    /**
     * Returns the value with every object in it rewritten as a stdClass.
     *
     * Recursion terminates because a cycle can only be serialized as an `r:` reference,
     * which the policy rejects before anything reaches this stage.
     */
    public function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof UnitEnum => $value,
            is_object($value) => $this->normalizeObject($value),
            is_array($value) => array_map($this->normalize(...), $value),
            default => $value,
        };
    }

    /**
     * Rewrites one object as a stdClass holding every property it declares.
     *
     * An object becomes a stdClass rather than an array so that one whose property names
     * are all numeric still encodes as a JSON object rather than a JSON array.
     */
    private function normalizeObject(object $object): stdClass
    {
        $properties = $this->describeProperties($object);
        $collidingNames = $this->collidingNames($properties);
        $normalized = new stdClass;

        foreach ($properties as ['name' => $name, 'owner' => $owner, 'value' => $value]) {
            $key = in_array($name, $collidingNames, strict: true) ? "{$owner}::{$name}" : $name;

            $normalized->{$key} = $this->normalize($value);
        }

        return $normalized;
    }

    /**
     * Lists an object's properties with the class each one belongs to.
     *
     * @return list<array{name: string, owner: string, value: mixed}>
     */
    private function describeProperties(object $object): array
    {
        $properties = [];

        foreach ((array) $object as $key => $value) {
            [$owner, $name] = $this->splitStorageKey((string) $key, $object::class);

            $properties[] = ['name' => $name, 'owner' => $owner, 'value' => $value];
        }

        return $properties;
    }

    /**
     * Splits a property's storage key into the class that owns it and its plain name.
     *
     * PHP stores a private property under "\0Declaring\0name" and a protected one under
     * "\0*\0name"; the NUL-delimited spelling is a storage detail, not data the user wrote.
     *
     * @return array{string, string}
     */
    private function splitStorageKey(string $key, string $objectClass): array
    {
        if (preg_match('/^\x00(?<owner>[^\x00]+)\x00(?<name>.*)$/s', $key, $matches) !== 1) {
            return [$objectClass, $key];
        }

        $owner = $matches['owner'];

        return [$owner === '*' ? $objectClass : $owner, $matches['name']];
    }

    /**
     * Returns the plain names that more than one property demangles to.
     *
     * A class redeclaring a parent's private property owns a second property of the same
     * name; qualifying both is what keeps either from overwriting the other.
     *
     * @param  list<array{name: string, owner: string, value: mixed}>  $properties
     * @return list<string>
     */
    private function collidingNames(array $properties): array
    {
        $occurrences = array_count_values(array_column($properties, 'name'));
        $colliding = array_keys(array_filter($occurrences, static fn (int $count): bool => $count > 1));

        // array_count_values turns a numeric property name into an integer key; the name it
        // came from was a string, so it is compared as one.
        return array_map(strval(...), $colliding);
    }
}
