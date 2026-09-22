<?php

declare(strict_types=1);

namespace Serialized\Conversion;

use Serialized\Tokenizer\Token;
use Serialized\Tokenizer\Tokenizer;
use Serialized\Tokenizer\TokenType;

/**
 * Writes each `S:` escaped string as the plain `s:` string its escapes spell.
 *
 * PHP 8.4 deprecated reading the `S` format, so the payload handed to unserialize() must
 * never contain it. The rewrite is built only from tokens validation already accepted, and
 * a declared `S:` length counts decoded bytes, so no rewritten token outgrows its original.
 */
final readonly class EscapedStringRewriter
{
    /**
     * Builds a rewriter, taking the tokenizer as a collaborator for testing.
     */
    public function __construct(
        private Tokenizer $tokenizer = new Tokenizer,
    ) {}

    /**
     * Returns the payload with every escaped string written in the plain form.
     *
     * Only a payload holding the bytes `S:` is lexed again; one that holds them inside an
     * ordinary string value costs that extra pass but comes back unchanged.
     */
    public function rewrite(string $payload): string
    {
        if (! str_contains($payload, 'S:')) {
            return $payload;
        }

        return $this->rewriteRange($payload, 0, strlen($payload));
    }

    /**
     * Rewrites the tokens lexed from one byte range of an already validated payload.
     */
    private function rewriteRange(string $payload, int $from, int $through): string
    {
        $rewritten = '';

        foreach ($this->tokenizer->tokenize($payload, $from, $through) as $token) {
            $rewritten .= $this->rewriteToken($payload, $token);
        }

        return $rewritten;
    }

    /**
     * Writes one token back out, in the plain form when it spells its bytes as escapes.
     *
     * A custom body is rewritten too, because the class's own unserialize() runs inside the
     * same call and would read an `S:` in it just the same.
     */
    private function rewriteToken(string $payload, Token $token): string
    {
        return match (true) {
            $token->type === TokenType::String && $token->decodedLiteral !== null => sprintf(
                's:%d:"%s";',
                strlen($token->decodedLiteral),
                $token->decodedLiteral,
            ),
            $token->type === TokenType::CustomObject => $this->rewriteCustomObject($payload, $token),
            default => $token->raw(),
        };
    }

    /**
     * Rewrites a custom object's body and declares the body's new length in its header.
     */
    private function rewriteCustomObject(string $payload, Token $token): string
    {
        $bodyStart = $token->literalOffset ?? $token->offset;
        $body = $this->rewriteRange($payload, $bodyStart, $bodyStart + ($token->literalLength ?? 0));
        $className = $token->className ?? '';

        return sprintf('C:%d:"%s":%d:{%s}', strlen($className), $className, strlen($body), $body);
    }
}
