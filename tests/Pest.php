<?php

declare(strict_types=1);

use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;
use Wobqqq\AegisInputSanitizer\Tests\Fixtures\User;
use Wobqqq\AegisInputSanitizer\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

function admin(): User
{
    return User::query()->create(['email' => 'admin@example.com', 'is_admin' => true]);
}

function editor(): User
{
    return User::query()->create(['email' => 'editor@example.com', 'is_admin' => false]);
}

/**
 * Saves the section through the core, the way the Aegis page does: validated, then the caches cleared.
 *
 * @param array<string, mixed> $values
 *
 * @return array<string, mixed>
 */
function sanitize(array $values = []): array
{
    return Aegis::save(
        InputSanitizerModule::KEY,
        array_replace(new InputSanitizerModule()->defaults(), ['enabled' => true], $values),
    );
}
