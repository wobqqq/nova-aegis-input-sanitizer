<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Scanning;

final readonly class ScanResult
{
    /**
     * @param list<Detection> $detections
     */
    public function __construct(public bool $blocked, public array $detections)
    {
    }

    public function score(): int
    {
        return count($this->detections);
    }
}
