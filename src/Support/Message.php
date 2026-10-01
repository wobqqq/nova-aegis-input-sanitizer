<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Support;

final class Message
{
    /**
     * @param array<string, float|int|string> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }
}
