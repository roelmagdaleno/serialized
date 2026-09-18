<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use Serialized\Diagnostics\Diagnostic;
use Throwable;

/**
 * Implemented by every exception this package throws, so one catch covers all of them.
 */
interface SerializedException extends Throwable
{
    /**
     * Null when the failure cannot be traced to a byte in the payload.
     */
    public function diagnostic(): ?Diagnostic;
}
