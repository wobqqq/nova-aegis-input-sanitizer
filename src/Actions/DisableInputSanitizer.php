<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Actions;

use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;

final readonly class DisableInputSanitizer
{
    /**
     * @return list<string> the settings that were reset to their defaults because the rules refused them
     */
    public function handle(): array
    {
        $values = array_replace(Aegis::settings(InputSanitizerModule::KEY), ['enabled' => false]);

        try {
            Aegis::save(InputSanitizerModule::KEY, $values);

            return [];
        } catch (ValidationException $validationException) {
            // A row the rules refuse must not keep the sanitizer on: its invalid values go back to their defaults.
            $invalid = array_values(array_unique(array_map(
                static fn (string $attribute): string => explode('.', $attribute)[0],
                array_keys($validationException->errors()),
            )));
            $defaults = array_intersect_key(new InputSanitizerModule()->defaults(), array_flip($invalid));

            Aegis::save(InputSanitizerModule::KEY, array_replace($values, $defaults, ['enabled' => false]));

            return array_keys($defaults);
        }
    }
}
