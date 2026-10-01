<?php

declare(strict_types=1);

use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;

it('tells a pattern that compiles from one that does not, without a warning', function (string $pattern, bool $compiles): void {
    expect(PatternMatcher::compiles($pattern))->toBe($compiles);
})->with([
    ['~<script~i', true],
    ['', false],
    ['~(unclosed~', false],
    ['no delimiters', false],
    ['/a/e', false],
]);

it('reads a pattern that cannot run as no match', function (): void {
    expect(PatternMatcher::matches('~(unclosed~', 'anything'))->toBeFalse()
        ->and(PatternMatcher::matches('~<script~i', '<SCRIPT>'))->toBeTrue()
        ->and(PatternMatcher::matches('~\w~u', "\xff"))->toBeFalse();
});

it('ships a default pattern that compiles for every category', function (Category $category): void {
    expect(PatternMatcher::compiles($category->defaultPattern()))->toBeTrue()
        ->and($category->setting())->toEndWith('_patterns')
        ->and($category->label())->not->toStartWith('aegis-input-sanitizer::');
})->with(Category::cases());
