<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Throwable;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;

/**
 * The settings every request reads: built once from the Aegis section, cached as an array.
 */
final class SettingsStore
{
    /**
     * Versioned: a release that changes what is cached bumps it.
     */
    public const string CACHE_KEY = 'aegis.input-sanitizer.settings.v1';

    private const int TTL = 3600;

    private ?InputSanitizerSettings $settings = null;

    public function __construct(private readonly Cache $cache, private readonly ViewFactory $views)
    {
    }

    public function settings(): InputSanitizerSettings
    {
        return $this->settings ??= $this->load();
    }

    /**
     * The settings as saved, read past the cache.
     */
    public function fresh(): InputSanitizerSettings
    {
        $settings = InputSanitizerSettings::fromArray(Aegis::settings(InputSanitizerModule::KEY));

        return $this->views->exists($settings->view) ? $settings : $settings->withView(InputSanitizerSettings::DEFAULT_VIEW);
    }

    public function forget(): void
    {
        $this->settings = null;

        try {
            $this->cache->forget(self::CACHE_KEY);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    private function load(): InputSanitizerSettings
    {
        try {
            $cached = $this->cache->remember(self::CACHE_KEY, self::TTL, fn (): array => $this->fresh()->toArray());
        } catch (Throwable) {
            $cached = null;
        }

        return is_array($cached) ? InputSanitizerSettings::fromArray($cached) : $this->fresh();
    }
}
