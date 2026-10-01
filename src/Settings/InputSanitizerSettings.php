<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Settings;

use Wobqqq\Aegis\Support\Values;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;

final readonly class InputSanitizerSettings
{
    public const string DEFAULT_VIEW = 'aegis-input-sanitizer::blocked';

    public const int DEFAULT_THRESHOLD = 1;

    public const int MAX_THRESHOLD = 1000;

    public const string VIEW_NAME = '/^(?:[A-Za-z0-9_-]+::)?[A-Za-z0-9_-]+(?:[.\/][A-Za-z0-9_-]+)*$/';

    public const string INPUT_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/';

    public const string HEADER_NAME = '/^[A-Za-z0-9-]+$/';

    /** Their values are never typed by a visitor and hold characters the patterns flag. */
    public const array ALWAYS_EXCLUDED_HEADERS = ['cookie', 'accept'];

    /**
     * @param array<string, string> $patterns category value => pattern, only the patterns that compile
     * @param array<string, true> $excludedInputs lower-case input names and dotted paths
     * @param array<string, true> $excludedHeaders lower-case header names
     */
    public function __construct(
        public bool $enabled,
        public bool $scanJson,
        public bool $scanNova,
        public bool $logBlocked,
        public int $blockThreshold,
        public string $view,
        public array $patterns,
        public array $excludedInputs,
        public array $excludedHeaders,
    ) {
    }

    /**
     * Reads the stored values again, whatever they are: the row may predate the rules or be written by hand.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $view = Values::string($values, 'view');
        $threshold = $values['block_threshold'] ?? null;
        $threshold = is_int($threshold) || (is_string($threshold) && ctype_digit($threshold)) ? (int)$threshold : 0;

        $patterns = [];

        foreach (Category::cases() as $category) {
            $pattern = Values::string($values, $category->setting());

            if (PatternMatcher::compiles($pattern)) {
                $patterns[$category->value] = $pattern;
            }
        }

        $headers = array_fill_keys(self::ALWAYS_EXCLUDED_HEADERS, true) + self::names($values, 'excluded_headers', self::HEADER_NAME);

        return new self(
            Values::bool($values, 'enabled'),
            Values::bool($values, 'scan_json'),
            Values::bool($values, 'scan_nova'),
            Values::bool($values, 'log_blocked', true),
            $threshold >= 1 && $threshold <= self::MAX_THRESHOLD ? $threshold : self::DEFAULT_THRESHOLD,
            PatternMatcher::matches(self::VIEW_NAME, $view) && strlen($view) <= 150 ? $view : self::DEFAULT_VIEW,
            $patterns,
            self::names($values, 'excluded_inputs', self::INPUT_NAME),
            $headers,
        );
    }

    public function withView(string $view): self
    {
        return new self(
            $this->enabled,
            $this->scanJson,
            $this->scanNova,
            $this->logBlocked,
            $this->blockThreshold,
            $view,
            $this->patterns,
            $this->excludedInputs,
            $this->excludedHeaders,
        );
    }

    /**
     * The stored shape, which fromArray() reads back: what the cache holds.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [
            'enabled' => $this->enabled,
            'scan_json' => $this->scanJson,
            'scan_nova' => $this->scanNova,
            'log_blocked' => $this->logBlocked,
            'block_threshold' => $this->blockThreshold,
            'view' => $this->view,
            'excluded_inputs' => $this->rows($this->excludedInputs),
            'excluded_headers' => $this->rows($this->excludedHeaders),
        ];

        foreach (Category::cases() as $category) {
            $values[$category->setting()] = $this->patterns[$category->value] ?? '';
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, true>
     */
    private static function names(array $values, string $key, string $format): array
    {
        $names = [];

        foreach (Values::column($values, $key, 'name') as $name) {
            $name = strtolower($name);

            if (strlen($name) <= 100 && PatternMatcher::matches($format, $name)) {
                $names[$name] = true;
            }
        }

        return $names;
    }

    /**
     * @param array<string, true> $names
     *
     * @return list<array{name: string}>
     */
    private function rows(array $names): array
    {
        return array_map(static fn (string $name): array => ['name' => $name], array_keys($names));
    }
}
