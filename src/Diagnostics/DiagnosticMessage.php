<?php

declare(strict_types=1);

namespace Serialized\Diagnostics;

/**
 * Assembles the human-readable exception message from a diagnostic.
 */
final class DiagnosticMessage
{
    /**
     * Builds the exception message: the reason, the caret snippet, then the fix.
     */
    public static function render(Diagnostic $diagnostic): string
    {
        return sprintf(
            "%s\n\n%s\n\nFix: %s",
            $diagnostic->reason,
            new SnippetRenderer()->render($diagnostic),
            $diagnostic->fix,
        );
    }
}
