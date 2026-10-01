<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;

final class DisableCommand extends Command
{
    /** @var string */
    protected $signature = 'aegis:input-sanitizer:disable';

    /** @var string */
    protected $description = 'Turn the Aegis Input Sanitizer off, for a site blocked by a pattern that is too broad.';

    public function handle(): int
    {
        $values = array_replace(Aegis::settings(InputSanitizerModule::KEY), ['enabled' => false]);

        try {
            Aegis::save(InputSanitizerModule::KEY, $values);
        } catch (ValidationException $e) {
            // A row the rules refuse must not keep the sanitizer on: its invalid values go back to their defaults.
            $invalid = array_values(array_unique(array_map(
                static fn (string $attribute): string => explode('.', $attribute)[0],
                array_keys($e->errors()),
            )));
            $defaults = array_intersect_key((new InputSanitizerModule())->defaults(), array_flip($invalid));

            Aegis::save(InputSanitizerModule::KEY, array_replace($values, $defaults, ['enabled' => false]));

            $this->components->warn(sprintf('Reset to their defaults: %s.', implode(', ', array_keys($defaults))));
        }

        $this->components->info('Aegis Input Sanitizer is off.');

        return self::SUCCESS;
    }
}
