<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;

/**
 * A pattern that does not compile would be skipped on every request: refuse it when it is saved.
 */
final class CompilablePattern implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !PatternMatcher::compiles(trim($value))) {
            $fail('aegis-input-sanitizer::input-sanitizer.validation.pattern')->translate();
        }
    }
}
