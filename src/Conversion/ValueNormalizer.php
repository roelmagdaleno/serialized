<?php

declare(strict_types=1);

namespace Serialized\Conversion;

use ReflectionReference;
use Serialized\Exceptions\UnrepresentableValueException;
use Serialized\PropertyName;
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
     * A back-reference has already been resolved by `unserialize()` into the value it
     * points at, so most of them need nothing here: the value is simply written out
     * again wherever it appears. One that points at an ancestor is different, because
     * the structure it describes has no end. That case is caught on the way down rather
     * than left to `json_encode()`, which would report a recursion no byte can be
     * blamed for -- and rather than left to this walk, which would not return.
     *
     * @param  string  $payload  the payload being converted, for the diagnostic
     * @param  int|null  $referenceOffset  byte position of the payload's first reference
     *
     * @throws UnrepresentableValueException when the value contains itself
     */
    public function normalize(mixed $value, string $payload = '', ?int $referenceOffset = null): mixed
    {
        return $this->normalizeValue($value, $payload, $referenceOffset, []);
    }

    /**
     * Rewrites one value, refusing any path that arrives back where it started.
     *
     * `$openReferences` holds the reference ids on the path currently being walked, not
     * every id seen. An id is dropped again on the way back up, so the same value
     * appearing twice side by side is written out twice, while a value appearing inside
     * itself is refused.
     *
     * @param  array<string, true>  $openReferences
     *
     * @throws UnrepresentableValueException when the value contains itself
     */
    private function normalizeValue(mixed $value, string $payload, ?int $referenceOffset, array $openReferences): mixed
    {
        return match (true) {
            $value instanceof UnitEnum => $value,
            is_object($value) => $this->normalizeObject($value, $payload, $referenceOffset, $openReferences),
            is_array($value) => $this->normalizeArray($value, $payload, $referenceOffset, $openReferences),
            default => $value,
        };
    }

    /**
     * Rewrites every element of an array, tracking the references on the way down.
     *
     * @param  array<array-key, mixed>  $value
     * @param  array<string, true>  $openReferences
     * @return array<array-key, mixed>
     *
     * @throws UnrepresentableValueException when the value contains itself
     */
    private function normalizeArray(array $value, string $payload, ?int $referenceOffset, array $openReferences): array
    {
        $normalized = [];

        foreach ($value as $key => $element) {
            $id = ReflectionReference::fromArrayElement($value, $key)?->getId();

            if ($id === null) {
                $normalized[$key] = $this->normalizeValue($element, $payload, $referenceOffset, $openReferences);

                continue;
            }

            if (isset($openReferences[$id])) {
                throw UnrepresentableValueException::circularReference($payload, $referenceOffset ?? 0);
            }

            $normalized[$key] = $this->normalizeValue(
                $element,
                $payload,
                $referenceOffset,
                [...$openReferences, $id => true],
            );
        }

        return $normalized;
    }

    /**
     * Rewrites one object as a stdClass holding every property it declares.
     *
     * An object becomes a stdClass rather than an array so that one whose property names
     * are all numeric still encodes as a JSON object rather than a JSON array.
     *
     * @param  array<string, true>  $openReferences
     */
    private function normalizeObject(object $object, string $payload, ?int $referenceOffset, array $openReferences): stdClass
    {
        $properties = $this->describeProperties($object);
        $collidingNames = $this->collidingNames($properties);
        $normalized = new stdClass;

        foreach ($properties as ['name' => $name, 'owner' => $owner, 'value' => $value]) {
            $key = in_array($name, $collidingNames, strict: true) ? "{$owner}::{$name}" : $name;

            $normalized->{$key} = $this->normalizeValue($value, $payload, $referenceOffset, $openReferences);
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
            $propertyName = PropertyName::fromStorageKey((string) $key, $object::class);

            $properties[] = ['name' => $propertyName->name, 'owner' => $propertyName->owner, 'value' => $value];
        }

        return $properties;
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
