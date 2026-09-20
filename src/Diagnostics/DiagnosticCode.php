<?php

declare(strict_types=1);

namespace Serialized\Diagnostics;

/**
 * Every way a payload can be refused, and the default wording for each one.
 *
 * The case is the stable identifier a consumer matches on — an i18n key, a telemetry
 * label — while `reason()` and `fix()` build the English a `Diagnostic` exposes. A
 * consumer that wants its own wording reads the code and the diagnostic's context map
 * instead, so the sentences here are a default, never the only way to describe a failure.
 */
enum DiagnosticCode: string
{
    case EmptyPayload = 'empty_payload';
    case UnknownTypePrefix = 'unknown_type_prefix';
    case TruncatedPayload = 'truncated_payload';
    case UnexpectedByte = 'unexpected_byte';
    case MalformedValue = 'malformed_value';
    case MalformedLength = 'malformed_length';
    case MalformedElementCount = 'malformed_element_count';
    case ElementCountMismatch = 'element_count_mismatch';
    case ImpossibleElementCount = 'impossible_element_count';
    case UnbalancedClose = 'unbalanced_close';
    case UnclosedStructure = 'unclosed_structure';
    case NonScalarKey = 'non_scalar_key';
    case RejectedByPhp = 'rejected_by_php';
    case ValueOverrunsDeclaredLength = 'value_overruns_declared_length';
    case TrailingBytes = 'trailing_bytes';
    case LengthMismatch = 'length_mismatch';
    case DisallowedClass = 'disallowed_class';
    case UnloadableClass = 'unloadable_class';
    case UnrestorableClass = 'unrestorable_class';
    case ContainsReference = 'contains_reference';
    case NonUtf8String = 'non_utf8_string';
    case NonBackedEnum = 'non_backed_enum';
    case UnusablePropertyName = 'unusable_property_name';
    case NonFiniteFloat = 'non_finite_float';
    case MaxBytesExceeded = 'max_bytes_exceeded';
    case MaxDepthExceeded = 'max_depth_exceeded';
    case MaxElementsExceeded = 'max_elements_exceeded';

    /**
     * Describes what went wrong, in one sentence.
     *
     * @param  int  $offset  the diagnostic's byte offset, which is never duplicated into the context
     * @param  array<string, string|int|bool|null>  $context  the facts behind the failure, keyed per case
     */
    public function reason(int $offset, array $context): string
    {
        return match ($this) {
            self::EmptyPayload => 'The payload is empty, so there is nothing to convert.',
            self::UnknownTypePrefix => sprintf(
                'Unknown type prefix "%s" at offset %d.',
                $this->stringFrom($context, 'prefix'),
                $offset,
            ),
            self::TruncatedPayload => sprintf(
                'Payload ends at offset %d while expecting "%s".',
                $offset,
                $this->stringFrom($context, 'expected'),
            ),
            self::UnexpectedByte => sprintf(
                'Expected "%s" at offset %d, found "%s".',
                $this->stringFrom($context, 'expected'),
                $offset,
                $this->stringFrom($context, 'found'),
            ),
            self::MalformedValue => sprintf(
                'Malformed %s literal "%s" at offset %d.',
                $this->stringFrom($context, 'typeLabel'),
                $this->stringFrom($context, 'literal'),
                $offset,
            ),
            self::MalformedLength => sprintf(
                'String length "%s" at offset %d is not a non-negative integer.',
                $this->stringFrom($context, 'literal'),
                $offset,
            ),
            self::MalformedElementCount => sprintf(
                'Array element count "%s" at offset %d is not a non-negative integer.',
                $this->stringFrom($context, 'literal'),
                $offset,
            ),
            self::ElementCountMismatch => sprintf(
                'The %s at offset %d declares %d element(s) but holds %d.',
                $this->stringFrom($context, 'structureLabel'),
                $offset,
                $this->integerFrom($context, 'declaredCount'),
                $this->integerFrom($context, 'actualCount'),
            ),
            self::ImpossibleElementCount => sprintf(
                'A structure at offset %d declares %s pairs, which %d remaining bytes cannot hold.',
                $offset,
                $this->stringFrom($context, 'declaredCount'),
                $this->integerFrom($context, 'remainingByteCount'),
            ),
            self::UnbalancedClose => sprintf(
                'The closing brace at offset %d closes an array that was never opened.',
                $offset,
            ),
            self::UnclosedStructure => sprintf(
                'The %s opened at offset %d is never closed.',
                $this->stringFrom($context, 'structureLabel'),
                $offset,
            ),
            self::NonScalarKey => sprintf(
                'A %s is used as %s at offset %d.',
                $this->stringFrom($context, 'keyTypeLabel'),
                $this->stringFrom($context, 'keySlot'),
                $offset,
            ),
            self::RejectedByPhp => sprintf(
                'PHP could not unserialize this payload at offset %d: %s',
                $offset,
                $this->stringFrom($context, 'phpMessage'),
            ),
            self::ValueOverrunsDeclaredLength => sprintf(
                'A value runs past the declared end of its body at offset %d.',
                $offset,
            ),
            self::TrailingBytes => sprintf(
                'The value ends before offset %d, but the payload continues.',
                $offset,
            ),
            self::LengthMismatch => sprintf(
                'String length mismatch at offset %d: declared %d bytes, found %d.',
                $offset,
                $this->integerFrom($context, 'declaredByteLength'),
                $this->integerFrom($context, 'foundByteLength'),
            ),
            self::DisallowedClass => sprintf(
                'Payload contains an object of class "%s" at offset %d. Objects are rejected by '
                .'default because unserializing them can invoke __wakeup() and __destruct() on '
                .'attacker-controlled data.',
                $this->stringFrom($context, 'className'),
                $offset,
            ),
            self::UnloadableClass => sprintf(
                'Class "%s" at offset %d is allowed but could not be loaded, so PHP would restore '
                .'it as a __PHP_Incomplete_Class rather than the class you allowed.',
                $this->stringFrom($context, 'className'),
                $offset,
            ),
            self::UnrestorableClass => sprintf(
                'Class "%s" at offset %d is allowed, but PHP cannot build a value of it: an object '
                .'token needs an instantiable class, and an enum token needs an enum.',
                $this->stringFrom($context, 'className'),
                $offset,
            ),
            self::ContainsReference => sprintf(
                'The payload contains a reference (R: or r:) at offset %d, which JSON cannot represent.',
                $offset,
            ),
            self::NonUtf8String => sprintf(
                'Non-UTF-8 bytes in the string at offset %d. JSON requires valid UTF-8.',
                $offset,
            ),
            self::NonBackedEnum => sprintf(
                'The enum case %s at offset %d is not backed, so it has no JSON representation.',
                $this->stringFrom($context, 'caseName'),
                $offset,
            ),
            self::UnusablePropertyName => sprintf(
                'The property name at offset %d holds a NUL byte that is not PHP\'s private or protected spelling.',
                $offset,
            ),
            self::NonFiniteFloat => sprintf(
                'Float %s at offset %d has no JSON representation.',
                $this->stringFrom($context, 'literal'),
                $offset,
            ),
            self::MaxBytesExceeded => sprintf(
                'The payload is %d bytes, over the configured limit of %d.',
                $this->integerFrom($context, 'actualBytes'),
                $this->integerFrom($context, 'configuredLimit'),
            ),
            self::MaxDepthExceeded => sprintf(
                'The payload nests %d levels deep, over the configured limit of %d.',
                $this->integerFrom($context, 'actualDepth'),
                $this->integerFrom($context, 'configuredLimit'),
            ),
            self::MaxElementsExceeded => sprintf(
                'The payload holds more than %d elements, the configured limit.',
                $this->integerFrom($context, 'configuredLimit'),
            ),
        };
    }

    /**
     * Tells the caller what to change to make the payload convertible.
     *
     * @param  int  $offset  the diagnostic's byte offset, which is never duplicated into the context
     * @param  array<string, string|int|bool|null>  $context  the facts behind the failure, keyed per case
     */
    public function fix(int $offset, array $context): string
    {
        return match ($this) {
            self::EmptyPayload => 'Pass a serialized string, for example i:42; or a:0:{}.',
            self::UnknownTypePrefix => 'Expected one of: N, b, i, d, s, a, O, C, R, r, E.',
            self::TruncatedPayload => sprintf(
                'Append the missing "%s", or check whether the payload was cut short in storage.',
                $this->stringFrom($context, 'expected'),
            ),
            self::UnexpectedByte => sprintf(
                'Replace the byte at offset %d with "%s".',
                $offset,
                $this->stringFrom($context, 'expected'),
            ),
            self::MalformedValue => sprintf(
                'Write a valid %s value, or correct the type prefix.',
                $this->stringFrom($context, 'typeLabel'),
            ),
            self::MalformedLength => 'Write the value\'s length in bytes, for example s:6:"Chrome";.',
            self::MalformedElementCount => 'Write the number of key/value pairs, for example a:2:{...}.',
            self::ElementCountMismatch => sprintf(
                'Change %s to %s, or correct the %s contents.',
                $this->structureHeaderFor($context, $this->integerFrom($context, 'declaredCount')),
                $this->structureHeaderFor($context, $this->integerFrom($context, 'actualCount')),
                $this->stringFrom($context, 'structureLabel'),
            ),
            self::ImpossibleElementCount => 'Correct the declared count to the number of pairs the structure actually holds.',
            self::UnbalancedClose => 'Remove the brace, or add the array header it was meant to close.',
            self::UnclosedStructure => 'Append the missing closing brace, or check whether the payload was cut short in storage.',
            self::NonScalarKey => sprintf(
                'Only an integer or a string can be %s; replace the value at offset %d with one of those.',
                $this->stringFrom($context, 'keySlot'),
                $offset,
            ),
            self::RejectedByPhp => 'Check the payload against the byte shown; it may have been altered in storage or transit.',
            self::ValueOverrunsDeclaredLength => 'Correct the declared length of the custom-serialized object so it covers its whole body.',
            self::TrailingBytes => 'Remove the trailing bytes, or wrap the values in an array so the payload holds one value.',
            self::LengthMismatch => $this->lengthMismatchFix($context),
            self::DisallowedClass => sprintf(
                'If you trust this payload, allow the class explicitly: '
                .'Serialized::make()->allowClasses([%s::class]).',
                $this->stringFrom($context, 'className'),
            ),
            self::UnloadableClass => sprintf(
                'Make sure %s is autoloadable in this process, or correct the name passed to allowClasses().',
                $this->stringFrom($context, 'className'),
            ),
            self::UnrestorableClass => sprintf(
                'Check the payload: %s is abstract, an interface, a trait, or an enum written as a '
                .'plain object, none of which can be restored.',
                $this->stringFrom($context, 'className'),
            ),
            self::ContainsReference => 'Serialize a copy of the referenced value instead of a reference to it.',
            self::NonUtf8String => 'Base64-encode this value before serializing it, or repair its encoding.',
            self::NonBackedEnum => 'Give the enum a backing type, or replace the case with a string before serializing.',
            self::UnusablePropertyName => 'Remove the NUL byte from the property name, or drop the property before serializing.',
            self::NonFiniteFloat => 'Replace the value with null, a string, or a finite number before serializing.',
            self::MaxBytesExceeded => sprintf(
                'Raise the limit with ->withMaxBytes(%d), or convert a smaller payload.',
                $this->integerFrom($context, 'actualBytes'),
            ),
            self::MaxDepthExceeded => sprintf(
                'Raise the limit with ->withMaxDepth(%d), or flatten the payload.',
                $this->integerFrom($context, 'actualDepth'),
            ),
            self::MaxElementsExceeded => 'Raise the limit with ->withMaxElements(), or convert less data.',
        };
    }

    /**
     * Spells the fix for a declared length that does not match the bytes present.
     *
     * A class name has no type letter to quote, so its wording names the numbers instead.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    private function lengthMismatchFix(array $context): string
    {
        $declaredByteLength = $this->integerFrom($context, 'declaredByteLength');
        $foundByteLength = $this->integerFrom($context, 'foundByteLength');
        $prefix = $this->nullableStringFrom($context, 'prefix');

        return $prefix === null
            ? sprintf(
                'Change the declared length from %d to %d, or restore the missing bytes.',
                $declaredByteLength,
                $foundByteLength,
            )
            : sprintf(
                'Change %1$s:%2$d to %1$s:%3$d, or restore the missing bytes in the value.',
                $prefix,
                $declaredByteLength,
                $foundByteLength,
            );
    }

    /**
     * Spells the header a structure would need to declare a given number of pairs.
     *
     * A structure header carries a class name when it opens an object and none when it
     * opens an array, which is the whole difference between the two spellings.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    private function structureHeaderFor(array $context, int $count): string
    {
        $className = $this->nullableStringFrom($context, 'className');

        return $className === null
            ? sprintf('a:%d', $count)
            : sprintf('O:%d:"%s":%d', strlen($className), $className, $count);
    }

    /**
     * Reads a textual fact, falling back to an empty string when the case declares none.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    private function stringFrom(array $context, string $key): string
    {
        return (string) ($context[$key] ?? '');
    }

    /**
     * Reads a fact that is absent for some payloads, such as the class name of an array header.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    private function nullableStringFrom(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Reads a numeric fact, falling back to zero when the case declares none.
     *
     * @param  array<string, string|int|bool|null>  $context
     */
    private function integerFrom(array $context, string $key): int
    {
        return (int) ($context[$key] ?? 0);
    }
}
