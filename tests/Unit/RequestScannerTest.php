<?php

declare(strict_types=1);

use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\Scanning\Detection;
use Wobqqq\AegisInputSanitizer\Scanning\RequestScanner;
use Wobqqq\AegisInputSanitizer\Scanning\Submission;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;

/**
 * @param array<string, true> $excludedInputs
 * @param array<string, true> $excludedHeaders
 */
function scannerSettings(int $threshold = 1, array $excludedInputs = [], array $excludedHeaders = []): InputSanitizerSettings
{
    return new InputSanitizerSettings(
        enabled: true,
        scanJson: true,
        scanNova: false,
        logBlocked: false,
        blockThreshold: $threshold,
        view: InputSanitizerSettings::DEFAULT_VIEW,
        patterns: [Category::XSS->value => '/<script/i'],
        excludedInputs: $excludedInputs,
        excludedHeaders: $excludedHeaders,
    );
}

it('finds a payload in the inputs, the headers and the path, decoded', function (): void {
    $result = new RequestScanner()->scan(new Submission(
        ['query' => ['q' => '%253Cscript%253E'], 'body' => ['post' => ['body' => '&lt;script&gt;']]],
        ['x-note' => ['<script>', null]],
        ['pages', '<script>'],
    ), scannerSettings(threshold: 10));

    expect(array_map(static fn (Detection $detection): string => $detection->source, $result->detections))
        ->toBe(['query.q', 'body.post.body', 'header.x-note', 'path.1'])
        ->and($result->blocked)->toBeFalse();
});

it('stops at the threshold', function (): void {
    $result = new RequestScanner()->scan(new Submission(['query' => ['a' => '<script>', 'b' => '<script>']]), scannerSettings());

    expect($result->blocked)->toBeTrue()->and($result->detections)->toHaveCount(1);
});

it('skips excluded inputs and headers', function (): void {
    $result = new RequestScanner()->scan(
        new Submission(['body' => ['post' => ['body' => '<script>']]], ['x-note' => ['<script>']]),
        scannerSettings(excludedInputs: ['post.body' => true], excludedHeaders: ['x-note' => true]),
    );

    expect($result->detections)->toBe([]);
});
