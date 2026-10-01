<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Checks;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Override;
use Wobqqq\Aegis\Aegis;
use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Contracts\Check;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;
use Wobqqq\AegisInputSanitizer\Support\Message;

/**
 * Reports what the sanitizer silently skips: a saved pattern that no longer compiles, a page that no longer exists.
 */
final readonly class PatternsCheck implements Check
{
    private const string KEY = 'input-sanitizer-patterns';

    public function __construct(private ViewFactory $views)
    {
    }

    #[Override]
    public function run(): CheckResult
    {
        $values = Aegis::settings(InputSanitizerModule::KEY);
        $settings = InputSanitizerSettings::fromArray($values);
        $label = $this->trans('check.label');

        if (!$settings->enabled) {
            return CheckResult::info(self::KEY, $label, $this->trans('check.off'));
        }

        $skipped = [];

        foreach (Category::cases() as $category) {
            $pattern = $values[$category->setting()] ?? null;

            if (is_string($pattern) && trim($pattern) !== '' && !PatternMatcher::compiles(trim($pattern))) {
                $skipped[] = $category->label();
            }
        }

        if ($skipped !== []) {
            return CheckResult::warn(self::KEY, $label, $this->trans('check.skipped', ['categories' => implode(', ', $skipped)]));
        }

        if (!$this->views->exists($settings->view)) {
            return CheckResult::warn(self::KEY, $label, $this->trans('check.view', ['view' => $settings->view]));
        }

        return CheckResult::pass(self::KEY, $label, $this->trans('check.pass'));
    }

    /**
     * @param array<string, string> $replace
     */
    private function trans(string $key, array $replace = []): string
    {
        return Message::get('aegis-input-sanitizer::input-sanitizer.' . $key, $replace);
    }
}
