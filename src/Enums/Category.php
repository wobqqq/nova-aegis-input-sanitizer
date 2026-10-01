<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Enums;

enum Category: string
{
    case XSS = 'xss';
    case ENCODED_XSS = 'encoded_xss';
    case COMMAND_INJECTION = 'command_injection';
    case PATH_TRAVERSAL = 'path_traversal';
    case SSTI = 'ssti';
    case NULL_BYTE = 'null_byte';
    case CSV_INJECTION = 'csv_injection';

    public function setting(): string
    {
        return $this->value . '_patterns';
    }

    public function label(): string
    {
        return (string)__('aegis-input-sanitizer::input-sanitizer.categories.' . $this->value);
    }

    public function defaultPattern(): string
    {
        return match ($this) {
            self::XSS => '~<\s*(script|iframe|object|embed|svg|meta|base|form|input|button)\b|javascript\s*:|vbscript\s*:|data\s*:\s*text/html|<[^>]+?\s+on[a-z]{3,30}\s*=~ix',
            self::ENCODED_XSS => '~(&lt;|%3c|%253c)\s*script~ix',
            self::COMMAND_INJECTION => '~(?:^|[;&\s])\s*(cmd|powershell|bash|sh|curl|wget|nc)\b|[a-z0-9]\s*\|\s*[a-z0-9]|&&|`[^`]+`|\$\([^)]*\)~ix',
            self::PATH_TRAVERSAL => '~\.\./|\.\.\\\\|%2e%2e%2f|%2e%2e%5c|/etc/passwd|windows/system32~ix',
            self::SSTI => '~\{\{.*?\}\}|\{%.*?%\}|\{!!.*?!!\}~sx',
            self::NULL_BYTE => '~\x00|%00|\\\\0|\\\\x00~ix',
            self::CSV_INJECTION => '~^\s*[=<$#]~x',
        };
    }
}
