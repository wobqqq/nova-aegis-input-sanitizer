<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Wobqqq\AegisInputSanitizer\Scanning\Detection;
use Wobqqq\AegisInputSanitizer\Scanning\RequestScanner;
use Wobqqq\AegisInputSanitizer\Scanning\ScanResult;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;
use Wobqqq\AegisInputSanitizer\Settings\SettingsStore;
use Wobqqq\AegisInputSanitizer\Support\Message;

final readonly class SanitizeInput
{
    /** The Aegis settings carry the patterns themselves: never scanned, or a pattern could not be fixed. */
    private const string AEGIS_API = 'nova-vendor/aegis';

    public function __construct(
        private SettingsStore $store,
        private RequestScanner $scanner,
        private ViewFactory $views,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = $this->store->settings();
            $result = $settings->enabled && $this->scans($request, $settings) ? $this->scanner->scan($request, $settings) : null;
        } catch (Throwable $throwable) {
            report($throwable);

            return $next($request);
        }

        if (!$result instanceof ScanResult || !$result->blocked) {
            return $next($request);
        }

        if ($settings->logBlocked) {
            $this->log($request, $result);
        }

        return $this->refuse($request, $settings);
    }

    private function scans(Request $request, InputSanitizerSettings $settings): bool
    {
        $path = trim($request->path(), '/');

        if ($this->under($path, self::AEGIS_API)) {
            return false;
        }

        if ($settings->scanNova) {
            return true;
        }

        $nova = trim(Nova::path(), '/');

        return !$this->under($path, 'nova-api') && !$this->under($path, 'nova-vendor') && ($nova === '' || !$this->under($path, $nova));
    }

    private function under(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function log(Request $request, ScanResult $result): void
    {
        // Where the payload was and what it looked like, never the value: it may hold a token or a password.
        $this->logger->warning('Aegis Input Sanitizer blocked a request.', [
            'ip' => $request->ip(),
            'method' => $request->getMethod(),
            'score' => $result->score(),
            'matches' => array_map(
                static fn (Detection $detection): string => sprintf(
                    '%s (%s)',
                    substr((string)preg_replace('/[^A-Za-z0-9_.\-]/', '?', $detection->source), 0, 100),
                    $detection->category->value,
                ),
                $result->detections,
            ),
        ]);
    }

    private function refuse(Request $request, InputSanitizerSettings $settings): Response
    {
        $message = Message::get('aegis-input-sanitizer::input-sanitizer.blocked.message');
        $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], Response::HTTP_BAD_REQUEST, $headers);
        }

        /** @var non-empty-list<view-string> $views */
        $views = array_values(array_unique([$settings->view, InputSanitizerSettings::DEFAULT_VIEW]));

        foreach ($views as $view) {
            try {
                return new Response($this->views->make($view)->render(), Response::HTTP_BAD_REQUEST, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return new Response($message, Response::HTTP_BAD_REQUEST, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
