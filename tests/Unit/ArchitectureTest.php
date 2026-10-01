<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Wobqqq\Aegis\Checks\CheckRegistry;
use Wobqqq\Aegis\Modules\ModuleRegistry;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\Aegis\Settings\SettingsRepository;
use Wobqqq\AegisInputSanitizer\Scanning\Detection;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;
use Wobqqq\AegisInputSanitizer\Scanning\ScanResult;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;

arch('every file declares strict types')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('value objects are immutable')
    ->expect([
        InputSanitizerSettings::class,
        Detection::class,
        ScanResult::class,
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
    ->ignoring(PatternMatcher::class);

arch('the module opens no network connection')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->not->toUse(['stream_socket_client', 'fsockopen', 'curl_init', 'file_get_contents', Http::class]);

arch('settings are read and saved through the Aegis contract, never the table')
    ->expect('Wobqqq\AegisInputSanitizer')
    ->not->toUse([
        AegisSetting::class,
        SettingsRepository::class,
        ModuleRegistry::class,
        CheckRegistry::class,
        DB::class,
    ]);
