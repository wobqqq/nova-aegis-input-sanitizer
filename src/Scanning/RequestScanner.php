<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Scanning;

use Generator;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;

/**
 * Scores the query, the body, the headers and the URL segments of a request together.
 */
final readonly class RequestScanner
{
    private const int DECODE_ROUNDS = 3;

    public function scan(Submission $submission, InputSanitizerSettings $settings): ScanResult
    {
        $detections = [];

        if ($settings->patterns === []) {
            return new ScanResult(false, []);
        }

        foreach ($this->values($submission, $settings) as [$source, $value]) {
            $decoded = str_replace(["\r", "\n"], '', $this->decode($value));

            foreach ($settings->patterns as $category => $pattern) {
                if (!PatternMatcher::matches($pattern, $decoded)) {
                    continue;
                }

                $detections[] = new Detection($source, Category::from($category));

                if (count($detections) >= $settings->blockThreshold) {
                    return new ScanResult(true, $detections);
                }
            }
        }

        return new ScanResult(false, $detections);
    }

    /**
     * @return Generator<int, array{0: string, 1: string}>
     */
    private function values(Submission $submission, InputSanitizerSettings $settings): Generator
    {
        foreach ($submission->inputs as $source => $data) {
            yield from $this->input($data, $source, '', $settings->excludedInputs);
        }

        foreach ($submission->headers as $name => $values) {
            $name = strtolower($name);

            if (isset($settings->excludedHeaders[$name])) {
                continue;
            }

            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    yield ['header.' . $name, $value];
                }
            }
        }

        foreach ($submission->segments as $index => $segment) {
            if ($segment !== '') {
                yield ['path.' . $index, $segment];
            }
        }
    }

    /**
     * Keys are scanned with their values; an excluded name skips the key and everything under it.
     *
     * @param array<mixed> $data
     * @param array<string, true> $excluded
     *
     * @return Generator<int, array{0: string, 1: string}>
     */
    private function input(array $data, string $source, string $path, array $excluded): Generator
    {
        foreach ($data as $key => $value) {
            $name = strtolower((string)$key);
            $dotted = $path === '' ? $name : $path . '.' . $name;

            if (isset($excluded[$name]) || isset($excluded[$dotted])) {
                continue;
            }

            if (is_string($key)) {
                yield [$source . '.' . $dotted, $key];
            }

            if (is_array($value)) {
                yield from $this->input($value, $source, $dotted, $excluded);
            } elseif (is_string($value) && $value !== '') {
                yield [$source . '.' . $dotted, $value];
            }
        }
    }

    private function decode(string $input): string
    {
        $decoded = $input;

        for ($round = 0; $round < self::DECODE_ROUNDS; $round++) {
            $next = urldecode($decoded);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
