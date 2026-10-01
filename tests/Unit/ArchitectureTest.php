<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('value objects are immutable')
    ->expect([
        Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings::class,
        Wobqqq\AegisInputSanitizer\Scanning\Detection::class,
        Wobqqq\AegisInputSanitizer\Scanning\ScanResult::class,
    ])
    ->toBeFinal()
    ->toBeReadonly();

arch('classes are final')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->classes()
    ->toBeFinal();

arch('enums back every shared code')
    ->expect('Wobqqq\AegisInputSanitizer\Enums')
    ->toBeStringBackedEnums();

arch('a pattern only runs through the matcher')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->not->toUse(['preg_match', 'preg_match_all', 'preg_grep', 'preg_split', 'preg_replace_callback'])
    ->ignoring(Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher::class);

arch('the module opens no network connection')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->not->toUse(['stream_socket_client', 'fsockopen', 'curl_init', 'file_get_contents', Illuminate\Support\Facades\Http::class]);

arch('settings are read and saved through the Aegis contract, never the table')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->not->toUse([
        Wobqqq\Aegis\Settings\AegisSetting::class,
        Wobqqq\Aegis\Settings\SettingsRepository::class,
        Wobqqq\Aegis\Modules\ModuleRegistry::class,
        Wobqqq\Aegis\Checks\CheckRegistry::class,
        Illuminate\Support\Facades\DB::class,
    ]);
