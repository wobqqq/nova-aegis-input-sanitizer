<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\Aegis\Checks\CheckRunner;
use Wobqqq\Aegis\Enums\Status;
use Wobqqq\Aegis\Modules\ModuleRegistry;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\Aegis\Settings\SettingsRepository;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;
use Wobqqq\AegisInputSanitizer\Settings\SettingsStore;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

function store(): SettingsStore
{
    return resolve(SettingsStore::class);
}

/**
 * @param array<string, mixed> $values
 */
function storeRaw(array $values): void
{
    AegisSetting::query()->updateOrCreate(['section' => InputSanitizerModule::KEY], ['values' => $values]);
}

it('adds its section to the Aegis settings, off and with a pattern for every kind of attack', function (): void {
    expect(resolve(ModuleRegistry::class)->get(InputSanitizerModule::KEY))->toBeInstanceOf(InputSanitizerModule::class);

    $values = Aegis::settings(InputSanitizerModule::KEY);

    expect($values)->toMatchArray(['enabled' => false, 'scan_json' => false, 'scan_nova' => false, 'block_threshold' => 1]);

    foreach (Category::cases() as $category) {
        expect($values[$category->setting()])->toBe($category->defaultPattern());
    }

    expect(store()->settings()->enabled)->toBeFalse()
        ->and(store()->settings()->patterns)->toHaveCount(count(Category::cases()));
});

it('draws its form in the Aegis page for the administrators only', function (): void {
    actingAs(editor())->getJson('/nova-vendor/aegis/settings')->assertForbidden();

    $sections = actingAs(admin())->getJson('/nova-vendor/aegis/settings')->assertOk()->json('sections');
    $section = collect(is_array($sections) ? $sections : [])->firstWhere('key', InputSanitizerModule::KEY);

    $fields = data_get($section, 'fields');

    expect(data_get($section, 'fields.0.name'))->toBe('enabled')
        ->and(collect(is_array($fields) ? $fields : [])->pluck('name')->all())->toContain('xss_patterns', 'excluded_inputs', 'excluded_headers');
});

it('accepts its own defaults', function (): void {
    expect(sanitize(['enabled' => false]))->toMatchArray(['enabled' => false]);
});

it('refuses a pattern that does not compile', function (string $pattern, bool $passes): void {
    try {
        sanitize(['xss_patterns' => $pattern]);
        $saved = true;
    } catch (ValidationException) {
        $saved = false;
    }

    expect($saved)->toBe($passes);
})->with([
    ['~<script~i', true],
    ['', true],
    ['~(unclosed~', false],
    ['no delimiters', false],
    [str_repeat('a', 1001), false],
]);

it('refuses invalid values', function (string $field, mixed $value): void {
    sanitize([$field => $value]);
})->throws(ValidationException::class)->with([
    ['block_threshold', 0],
    ['block_threshold', 1001],
    ['enabled', 'sometimes'],
    ['scan_json', 'yes please'],
    ['view', 'missing-view'],
    ['view', '../../etc/passwd'],
    ['view', 'aegis-input-sanitizer::blocked' . str_repeat('x', 150)],
    ['excluded_headers', [['name' => "X-Bad\r\n"]]],
    ['excluded_inputs', [['name' => '<script>']]],
    ['excluded_inputs', 'not a table'],
]);

it('accepts valid excluded names and an existing view', function (): void {
    $saved = sanitize([
        'view' => 'custom-blocked',
        'excluded_headers' => [['name' => 'X-Template']],
        'excluded_inputs' => [['name' => 'content.body'], ['name' => '_token']],
    ]);

    expect($saved['view'])->toBe('custom-blocked')
        ->and(store()->settings()->excludedInputs)->toBe(['content.body' => true, '_token' => true])
        ->and(store()->settings()->excludedHeaders)->toBe(['cookie' => true, 'accept' => true, 'x-template' => true]);
});

it('applies the settings as soon as they are saved', function (): void {
    sanitize(['block_threshold' => 2]);

    expect(store()->settings()->blockThreshold)->toBe(2);

    sanitize(['block_threshold' => 5]);

    expect(store()->settings()->blockThreshold)->toBe(5)
        ->and(Cache::get(SettingsStore::CACHE_KEY))->toBeArray();
});

it('applies a row saved without the core event, and a deleted row', function (): void {
    sanitize(['block_threshold' => 2]);
    expect(store()->settings()->blockThreshold)->toBe(2);

    storeRaw(array_replace((new InputSanitizerModule())->defaults(), ['enabled' => true, 'block_threshold' => 9]));
    expect(store()->settings()->blockThreshold)->toBe(9);

    AegisSetting::query()->where('section', InputSanitizerModule::KEY)->firstOrFail()->delete();
    resolve(SettingsRepository::class)->flush();
    expect(store()->settings()->enabled)->toBeFalse();
});

it('ignores the saves of other sections', function (): void {
    sanitize(['block_threshold' => 3]);
    expect(store()->settings()->blockThreshold)->toBe(3);

    resolve(SettingsRepository::class)->save('hardening', resolve(ModuleRegistry::class)->getOrFail('hardening')->defaults());

    expect(Cache::get(SettingsStore::CACHE_KEY))->toBeArray()->toHaveKey('block_threshold', 3);
});

it('falls back to safe values for a broken stored row', function (): void {
    $settings = InputSanitizerSettings::fromArray([
        'enabled' => 'on',
        'view' => '../../secret',
        'block_threshold' => 'many',
        'excluded_inputs' => 'not a table',
        'excluded_headers' => [['name' => "x\r\nbad"], ['name' => 'X-Ok'], 'row'],
        'xss_patterns' => '~(broken~',
        'ssti_patterns' => ['not', 'a', 'string'],
    ]);

    expect($settings->enabled)->toBeTrue()
        ->and($settings->view)->toBe(InputSanitizerSettings::DEFAULT_VIEW)
        ->and($settings->blockThreshold)->toBe(InputSanitizerSettings::DEFAULT_THRESHOLD)
        ->and($settings->excludedInputs)->toBe([])
        ->and($settings->excludedHeaders)->toBe(['cookie' => true, 'accept' => true, 'x-ok' => true])
        ->and($settings->patterns)->toBe([])
        ->and($settings->logBlocked)->toBeTrue()
        ->and(InputSanitizerSettings::fromArray(['block_threshold' => '7'])->blockThreshold)->toBe(7)
        ->and(InputSanitizerSettings::fromArray(['block_threshold' => 5000])->blockThreshold)->toBe(InputSanitizerSettings::DEFAULT_THRESHOLD);
});

it('gives blocked requests the built-in page when the saved one is gone', function (): void {
    storeRaw(array_replace((new InputSanitizerModule())->defaults(), ['enabled' => true, 'view' => 'deleted-view']));

    expect(store()->settings()->view)->toBe(InputSanitizerSettings::DEFAULT_VIEW);
});

it('reads the cached settings back the way it wrote them', function (): void {
    sanitize(['block_threshold' => 4, 'scan_json' => true, 'excluded_inputs' => [['name' => 'Body']]]);
    $written = store()->fresh();

    expect(InputSanitizerSettings::fromArray($written->toArray()))->toEqual($written);
});

it('rebuilds a cached entry another version wrote in another shape', function (): void {
    sanitize(['block_threshold' => 7]);
    Cache::put(SettingsStore::CACHE_KEY, 'serialized object of an older release');
    app()->forgetScopedInstances();

    expect(store()->settings()->blockThreshold)->toBe(7);
});

it('reads the section directly when the cache is down', function (): void {
    sanitize(['block_threshold' => 6]);

    /** @var CacheRepository&Mockery\MockInterface $cache */
    $cache = Mockery::mock(CacheRepository::class);
    $cache->allows('remember')->andThrow(new RuntimeException('cache down'));
    $cache->allows('forget')->andThrow(new RuntimeException('cache down'));
    $store = new SettingsStore($cache, resolve('view'));

    expect($store->settings()->blockThreshold)->toBe(6);

    $store->forget();
    expect($store->settings()->blockThreshold)->toBe(6);
});

it('saves through the Aegis page and refuses a broken pattern there', function (): void {
    $values = array_replace((new InputSanitizerModule())->defaults(), ['enabled' => true, 'block_threshold' => 3]);

    actingAs(admin())->putJson('/nova-vendor/aegis/settings/input-sanitizer', ['values' => $values])->assertOk();
    expect(store()->settings()->blockThreshold)->toBe(3);

    putJson('/nova-vendor/aegis/settings/input-sanitizer', ['values' => ['xss_patterns' => '~(~'] + $values])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('xss_patterns');
});

it('shows on the dashboard whether it is on', function (): void {
    $status = static fn (): ?Wobqqq\Aegis\Checks\CheckResult => collect(resolve(CheckRunner::class)->modules())->firstWhere('key', InputSanitizerModule::KEY);

    expect($status()?->status)->toBe(Status::WARN);

    sanitize(['block_threshold' => 2]);
    expect($status()?->status)->toBe(Status::PASS)
        ->and($status()?->message)->toContain('7 patterns')->toContain('score of 2');

    sanitize(array_fill_keys(array_map(static fn (Category $category): string => $category->setting(), Category::cases()), ''));
    expect($status()?->status)->toBe(Status::WARN)->and($status()?->message)->toContain('nothing is blocked');
});

it('warns about a saved pattern it skips and a page that is gone', function (): void {
    $check = static fn (): ?Wobqqq\Aegis\Checks\CheckResult => collect(resolve(CheckRunner::class)->checks())->firstWhere('key', 'input-sanitizer-patterns');

    expect($check()?->status)->toBe(Status::INFO);

    sanitize();
    expect($check()?->status)->toBe(Status::PASS);

    storeRaw(array_replace((new InputSanitizerModule())->defaults(), ['enabled' => true, 'xss_patterns' => '~(~', 'null_byte_patterns' => '~[~']));
    expect($check()?->status)->toBe(Status::WARN)->and($check()?->message)->toContain('XSS patterns, Null byte patterns');

    storeRaw(array_replace((new InputSanitizerModule())->defaults(), ['enabled' => true, 'view' => 'deleted-view']));
    expect($check()?->status)->toBe(Status::WARN)->and($check()?->message)->toContain('deleted-view');
});
