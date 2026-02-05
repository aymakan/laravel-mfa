<?php

declare(strict_types=1);

namespace Aymakan\Mfa;

use Aymakan\Mfa\Contracts\MfaDriverInterface;
use Aymakan\Mfa\Http\Middleware\EnsureMfaVerified;
use Aymakan\Mfa\Verification\CacheVerificationStore;
use Aymakan\Mfa\Verification\DatabaseVerificationStore;
use Aymakan\Mfa\Verification\SessionVerificationStore;
use Aymakan\Mfa\Verification\VerificationStoreInterface;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class MfaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mfa.php', 'mfa');

        $this->app->singleton(MfaManager::class, function (Application $app) {
            return new MfaManager($app['config']['mfa']);
        });

        $this->app->alias(MfaManager::class, 'mfa');

        $this->app->bind(VerificationStoreInterface::class, function (Application $app) {
            $config = $app['config']['mfa']['verification'] ?? [];
            $store = $config['store'] ?? 'session';

            if ($store === 'cache') {
                return new CacheVerificationStore(
                    $app['cache.store'],
                    $config
                );
            }

            if ($store === 'database') {
                return new DatabaseVerificationStore(
                    $app['db']->connection(),
                    $config
                );
            }

            return new SessionVerificationStore(
                $app['session.store'],
                $config
            );
        });

        $this->app->bind(MfaDriverInterface::class, function (Application $app) {
            return $app->make(MfaManager::class)->driver();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mfa.php' => $this->app->configPath('mfa.php'),
            ], 'mfa-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'mfa-migrations');
        }

        $this->registerMiddleware();
        $this->registerRoutes();
        $this->registerLogoutListener();
        $this->registerRateLimiter();
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $alias = $this->app['config']->get('mfa.middleware_alias', 'mfa.verified');
        $router->aliasMiddleware($alias, EnsureMfaVerified::class);
    }

    protected function registerRoutes(): void
    {
        $prefix = $this->app['config']->get('mfa.routes.prefix', 'mfa');
        $middleware = $this->app['config']->get('mfa.routes.middleware', ['api', 'auth:sanctum']);

        /** @var Router $router */
        $this->app['router']->group([
            'prefix' => $prefix,
            'middleware' => $middleware,
        ], function () {
            require __DIR__ . '/../routes/mfa.php';
        });
    }

    protected function registerLogoutListener(): void
    {
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user !== null) {
                $this->app->make(VerificationStoreInterface::class)->clear($event->user);
            }
        });
    }

    protected function registerRateLimiter(): void
    {
        RateLimiter::for('mfa', function ($request) {
            $user = $request->user();

            if (!$user) {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($request->ip());
            }

            $config = $this->app['config']['mfa']['rate_limit'] ?? [];
            $maxAttempts = $config['max_attempts'] ?? 5;
            $decayMinutes = $config['decay_minutes'] ?? 2;

            return \Illuminate\Cache\RateLimiting\Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($user->getAuthIdentifier());
        });
    }
}
