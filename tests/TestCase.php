<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Wobqqq\Aegis\AegisServiceProvider;
use Wobqqq\Aegis\Nova\AegisTool;
use Wobqqq\AegisInputSanitizer\InputSanitizerServiceProvider;
use Wobqqq\AegisInputSanitizer\Tests\Fixtures\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Admin');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Nova::$tools = [];
        Nova::tools([new AegisTool()]);

        Gate::define(AegisTool::GATE, static fn (User $user): bool => $user->is_admin);
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [\Inertia\ServiceProvider::class, NovaCoreServiceProvider::class, AegisServiceProvider::class, InputSanitizerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('aegis.users.model', User::class);
        $app['config']->set('aegis.audit.schedule', false);
        $app['view']->addLocation(__DIR__ . '/Fixtures/views');
    }

    protected function defineRoutes($router): void
    {
        $echo = static fn (Request $request): JsonResponse => new JsonResponse(['ok' => true, 'input' => $request->all()]);

        $router->any('/page/{path?}', static fn (): string => 'content')->where('path', '.*');
        $router->any('/api/comments', $echo);
        $router->any('/nova/resources/posts', static fn (): string => 'nova');
        $router->any('/admin/resources/posts', static fn (): string => 'admin');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/wobqqq/nova-aegis/database/migrations');
    }
}
