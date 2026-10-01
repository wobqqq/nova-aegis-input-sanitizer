<?php

declare(strict_types=1);

use Wobqqq\AegisInputSanitizer\Support\Message;

it('returns the translated line', function (): void {
    expect(Message::get('aegis-input-sanitizer::input-sanitizer.label'))->toBe(__('aegis-input-sanitizer::input-sanitizer.label'))
        ->and(Message::get('aegis-input-sanitizer::input-sanitizer.label'))->not->toBe('aegis-input-sanitizer::input-sanitizer.label');
});

it('answers the key itself for a key that names a group of lines', function (): void {
    expect(Message::get('aegis-input-sanitizer::input-sanitizer.fields'))->toBe('aegis-input-sanitizer::input-sanitizer.fields');
});
