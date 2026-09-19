<?php

declare(strict_types=1);

namespace Tests\Support;

use Serializable;

/**
 * A class serialized through the legacy Serializable interface, so it is written as a C: token.
 *
 * Its body is whatever serialize() produced, which the package has to validate rather than
 * trust: unserialize() hands those bytes straight to unserialize() below.
 */
final class LegacyBox implements Serializable
{
    public function __construct(public mixed $contents = null) {}

    /**
     * Writes the contents as the custom body PHP stores after the C: header.
     */
    public function serialize(): string
    {
        return serialize($this->contents);
    }

    /**
     * Rebuilds the contents from the custom body, allowing nothing to be instantiated.
     */
    public function unserialize(string $data): void
    {
        $this->contents = unserialize($data, ['allowed_classes' => false]);
    }

    /**
     * Kept alongside serialize() so PHP 8.1+ still writes this class as a C: token.
     */
    public function __serialize(): array
    {
        return ['contents' => $this->contents];
    }

    /**
     * @param  array{contents: mixed}  $data
     */
    public function __unserialize(array $data): void
    {
        $this->contents = $data['contents'];
    }
}
