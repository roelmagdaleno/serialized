<?php

declare(strict_types=1);

namespace Serialized\Policy;

use ReflectionClass;
use Serialized\Tokenizer\TokenType;

/**
 * The single place that decides whether PHP can rebuild a value of a named class.
 *
 * Allowing a class is a promise that unserializing it yields that class. PHP keeps that
 * promise only for a class it can load and instantiate: it answers an unknown name with a
 * __PHP_Incomplete_Class, and an abstract class, interface or trait with a raw Error.
 */
final readonly class ClassRestorability
{
    /**
     * Tells whether the class is known to this process at all.
     *
     * @phpstan-assert-if-true class-string $className
     */
    public function isLoadable(string $className): bool
    {
        return class_exists($className);
    }

    /**
     * Tells whether a token of this type can actually produce a value of the class.
     *
     * An enum is restored by looking its case up rather than by instantiation, so it is
     * the one kind of class that is restorable without being instantiable. The question
     * only makes sense for a class that is loaded, so the answer is no for one that is not.
     */
    public function canRestore(string $className, TokenType $type): bool
    {
        if ($type === TokenType::Enum) {
            return enum_exists($className);
        }

        return $this->isLoadable($className) && new ReflectionClass($className)->isInstantiable();
    }
}
