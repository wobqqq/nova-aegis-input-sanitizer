<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Scanning;

use Wobqqq\AegisInputSanitizer\Enums\Category;

final readonly class Detection
{
    /**
     * @param string $source where the value came from (`query.q`, `header.user-agent`, `path.2`), never the value
     */
    public function __construct(public string $source, public Category $category)
    {
    }
}
