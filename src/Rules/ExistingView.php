<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Override;

final class ExistingView implements ValidationRule
{
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !resolve(ViewFactory::class)->exists($value)) {
            $fail('aegis-input-sanitizer::input-sanitizer.validation.view')->translate();
        }
    }
}
