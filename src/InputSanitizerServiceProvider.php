<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Wobqqq\Aegis\Aegis;
use Wobqqq\Aegis\Events\SettingsSaved;
use Wobqqq\AegisInputSanitizer\Checks\PatternsCheck;
use Wobqqq\AegisInputSanitizer\Console\DisableCommand;
use Wobqqq\AegisInputSanitizer\Http\Middleware\SanitizeInput;
use Wobqqq\AegisInputSanitizer\Settings\SettingsStore;

final class InputSanitizerServiceProvider extends ServiceProvider
{
    /** Saved by hand or by an older core that dispatches no SettingsSaved. */
    private const SETTING_MODEL_EVENTS = [
        'eloquent.saved: Wobqqq\Aegis\Settings\AegisSetting',
        'eloquent.deleted: Wobqqq\Aegis\Settings\AegisSetting',
    ];

    public function register(): void
    {
        // Scoped, not a singleton: a long-running worker reads the settings again on every request.
        $this->app->scoped(SettingsStore::class, static fn (Application $app): SettingsStore => new SettingsStore(
            self::cache($app),
            $app->make('view'),
        ));
    }

    public function boot(Dispatcher $events): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'aegis-input-sanitizer');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'aegis-input-sanitizer');

        Aegis::module(new InputSanitizerModule());
        Aegis::check($this->app->make(PatternsCheck::class));

        $events->listen(SettingsSaved::class, function (SettingsSaved $event): void {
            if ($event->section === InputSanitizerModule::KEY) {
                $this->app->make(SettingsStore::class)->forget();
            }
        });
        $events->listen(self::SETTING_MODEL_EVENTS, function (mixed $setting): void {
            if (!is_object($setting) || ($setting->section ?? null) === InputSanitizerModule::KEY) {
                $this->app->make(SettingsStore::class)->forget();
            }
        });

        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel) {
            $kernel->pushMiddleware(SanitizeInput::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/aegis-input-sanitizer')], 'aegis-input-sanitizer-views');
            $this->commands([DisableCommand::class]);
        }
    }

    private static function cache(Application $app): Cache
    {
        $store = $app->make(Config::class)->get('aegis.cache_store');

        return $app->make(CacheFactory::class)->store(is_string($store) && $store !== '' ? $store : null);
    }
}
