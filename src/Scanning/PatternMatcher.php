<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Scanning;

/**
 * The only place an administrator's pattern is run.
 */
final class PatternMatcher
{
    public static function compiles(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * A pattern that does not compile or gives up (backtrack, recursion or JIT stack limit) is no match:
     * an administrator's mistake must never block or break the application.
     */
    public static function matches(string $pattern, string $subject): bool
    {
        set_error_handler(static fn (): bool => true);

        try {
            return preg_match($pattern, $subject) === 1;
        } finally {
            restore_error_handler();
        }
    }
}
