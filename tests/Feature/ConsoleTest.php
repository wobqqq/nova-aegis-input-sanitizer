<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Wobqqq\Aegis\Aegis;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;
use Wobqqq\AegisInputSanitizer\Settings\SettingsStore;

use function Pest\Laravel\get;

it('turns itself off from the console and keeps its other settings', function (): void {
    sanitize(['scan_json' => true, 'xss_patterns' => '~<script~i']);
    get('/page?q=' . urlencode('<script>'))->assertBadRequest();

    expect(Artisan::call('aegis:input-sanitizer:disable'))->toBe(0)
        ->and(Artisan::output())->toContain('Aegis Input Sanitizer is off.')
        ->and(Aegis::settings(InputSanitizerModule::KEY))->toMatchArray(['enabled' => false, 'scan_json' => true, 'xss_patterns' => '~<script~i'])
        ->and(resolve(SettingsStore::class)->settings()->enabled)->toBeFalse();

    get('/page?q=' . urlencode('<script>'))->assertOk();
});

it('turns itself off even when the saved row breaks the rules', function (): void {
    AegisSetting::query()->create(['section' => InputSanitizerModule::KEY, 'values' => array_replace(
        (new InputSanitizerModule())->defaults(),
        ['enabled' => true, 'block_threshold' => 0, 'ssti_patterns' => '~(~', 'excluded_inputs' => [['name' => '<bad>']], 'view' => 'custom-blocked'],
    )]);

    expect(Artisan::call('aegis:input-sanitizer:disable'))->toBe(0)
        ->and(Artisan::output())->toContain('block_threshold')->toContain('excluded_inputs');

    $values = Aegis::settings(InputSanitizerModule::KEY);

    expect($values)->toMatchArray(['enabled' => false, 'block_threshold' => 1, 'excluded_inputs' => [], 'view' => 'custom-blocked'])
        ->and($values['ssti_patterns'])->toBe((new InputSanitizerModule())->defaults()['ssti_patterns']);
});
