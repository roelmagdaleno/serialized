<?php

declare(strict_types=1);

namespace Serialized\Tokenizer;

/**
 * The kinds of token a serialized payload is made of, keyed by their prefix byte.
 */
enum TokenType: string
{
    case Null = 'N';
    case Boolean = 'b';
    case Integer = 'i';
    case Float = 'd';
    case String = 's';
    case Array = 'a';
    case Close = '}';
    case Object = 'O';
    case CustomObject = 'C';
    case Reference = 'R';
    case ValueReference = 'r';
    case Enum = 'E';

    /**
     * Tells whether a token of this type may be used as an array key.
     *
     * PHP array keys are only ever integers or strings, whatever the payload claims.
     */
    public function isValidArrayKey(): bool
    {
        return $this === self::Integer || $this === self::String;
    }

    /**
     * Tells whether a token of this type opens a brace the parser must see closed.
     */
    public function opensStructure(): bool
    {
        return $this === self::Array || $this === self::Object;
    }

    /**
     * Tells whether a token of this type points back at an earlier value.
     */
    public function isReference(): bool
    {
        return $this === self::Reference || $this === self::ValueReference;
    }

    /**
     * Returns the human-readable name used in diagnostics.
     */
    public function label(): string
    {
        return match ($this) {
            self::Null => 'null',
            self::Boolean => 'boolean',
            self::Integer => 'integer',
            self::Float => 'float',
            self::String => 'string',
            self::Array => 'array',
            self::Close => 'array close',
            self::Object => 'object',
            self::CustomObject => 'custom-serialized object',
            self::Reference => 'reference',
            self::ValueReference => 'value reference',
            self::Enum => 'enum',
        };
    }
}
