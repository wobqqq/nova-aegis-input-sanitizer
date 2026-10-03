<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Scanning;

/**
 * The parts of a request the scanner reads, taken by the middleware.
 */
final readonly class Submission
{
    /**
     * @param array<string, array<mixed>> $inputs the query, the form body or the JSON body, keyed by source
     * @param array<string, list<string|null>> $headers
     * @param list<string> $segments the URL path segments
     */
    public function __construct(
        public array $inputs,
        public array $headers = [],
        public array $segments = [],
    ) {
    }
}
