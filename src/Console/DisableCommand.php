<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Console;

use Illuminate\Console\Command;
use Wobqqq\AegisInputSanitizer\Actions\DisableInputSanitizer;

final class DisableCommand extends Command
{
    /** @var string */
    protected $signature = 'aegis:input-sanitizer:disable';

    /** @var string */
    protected $description = 'Turn the Aegis Input Sanitizer off, for a site blocked by a pattern that is too broad.';

    public function handle(DisableInputSanitizer $disable): int
    {
        $reset = $disable->handle();

        if ($reset !== []) {
            $this->components->warn(sprintf('Reset to their defaults: %s.', implode(', ', $reset)));
        }

        $this->components->info('Aegis Input Sanitizer is off.');

        return self::SUCCESS;
    }
}
