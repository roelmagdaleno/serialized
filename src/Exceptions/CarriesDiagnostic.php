<?php

declare(strict_types=1);

namespace Serialized\Exceptions;

use Serialized\Diagnostics\Diagnostic;
use Serialized\Diagnostics\DiagnosticMessage;

/**
 * Shared plumbing for the exceptions that point at a byte.
 *
 * A trait rather than a base class because these exceptions extend different SPL
 * parents — a malformed payload is an argument problem, a rejected one is not.
 * The constructor is private so an exception of this kind cannot exist without a
 * diagnostic, which is what lets diagnostic() narrow the interface's nullable return.
 */
trait CarriesDiagnostic
{
    /**
     * Private so an exception of this kind can only be built from a diagnostic.
     */
    private function __construct(string $message, private readonly Diagnostic $diagnostic)
    {
        parent::__construct($message);
    }

    /**
     * Returns where the payload went wrong, why, and how to fix it.
     */
    public function diagnostic(): Diagnostic
    {
        return $this->diagnostic;
    }

    /**
     * Builds the exception with its message rendered from the diagnostic.
     */
    private static function fromDiagnostic(Diagnostic $diagnostic): static
    {
        return new static(DiagnosticMessage::render($diagnostic), $diagnostic);
    }
}
