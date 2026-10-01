<?php

declare(strict_types=1);

return [
    'label' => 'Input Sanitizer',
    'description' => 'Refuses a request whose query, body, headers or URL carry an XSS, injection or traversal payload.',

    'fields' => [
        'enabled' => 'Scan the requests',
        'block_threshold' => 'Block at score',
        'view' => 'Page shown to a blocked request',
        'scan_json' => 'Scan JSON request bodies',
        'scan_nova' => 'Scan Nova requests too',
        'log_blocked' => 'Log blocked requests',
        'excluded_inputs' => 'Inputs never scanned',
        'excluded_headers' => 'Headers never scanned',
        'name' => 'Name',
    ],

    'help' => [
        'enabled' => 'Nothing is blocked until this is on. Turn it off from the console with php artisan aegis:input-sanitizer:disable.',
        'block_threshold' => 'Every pattern that matches a value adds one; the request is refused once the whole request reaches this score.',
        'view' => 'A Blade view name; the built-in page is used when it does not exist.',
        'scan_json' => 'Off: JSON bodies pass unscanned (their query string, headers and URL are still scanned). Turn it on once your APIs send no HTML.',
        'scan_nova' => "Nova's rich-text fields send HTML on purpose. The Aegis settings themselves are never scanned.",
        'log_blocked' => 'Writes the IP, the method and where the payload was, never the value.',
        'patterns' => 'One PCRE pattern with its delimiters. A pattern that does not compile is refused; one that gives up on a long input is no match.',
        'excluded_inputs' => "Input names or dotted paths (content, post.body), case-insensitive: a rich-text editor's field goes here.",
        'excluded_headers' => 'Cookie and Accept are never scanned.',
    ],

    'categories' => [
        'xss' => 'XSS patterns',
        'encoded_xss' => 'Encoded XSS patterns',
        'command_injection' => 'Command injection patterns',
        'path_traversal' => 'Path traversal patterns',
        'ssti' => 'Template injection patterns',
        'null_byte' => 'Null byte patterns',
        'csv_injection' => 'CSV injection patterns',
    ],

    'status' => [
        'off' => 'The Input Sanitizer is off.',
        'no_patterns' => 'The Input Sanitizer is on, but no pattern is in use: nothing is blocked.',
        'on' => 'Scans every request with :count patterns and blocks at a score of :threshold.',
    ],

    'check' => [
        'label' => 'Input Sanitizer patterns',
        'off' => 'The Input Sanitizer is off.',
        'skipped' => 'Saved patterns that do not compile are skipped: :categories.',
        'view' => 'The page ":view" does not exist; blocked requests get the built-in page.',
        'pass' => 'Every saved pattern compiles.',
    ],

    'validation' => [
        'pattern' => 'The :attribute is not a valid PCRE pattern with delimiters.',
        'view' => 'There is no view named :attribute.',
    ],

    'blocked' => [
        'title' => 'Bad request',
        'message' => 'The request was refused because it contains input that is not allowed.',
    ],
];
