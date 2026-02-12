<?php

namespace Ingenius\Payforms\Providers;

use Ingenius\Core\Http\Middleware\InitializeTenancyByRequestData;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Payforms';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapTenantRoutes();
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(function () {
            require __DIR__.'/../../routes/web.php';
        });
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->name('api.')
            ->group(function () {
                require __DIR__.'/../../routes/api.php';
            });
    }

    /**
     * Define the "tenant" routes for the application.
     *
     * These routes all receive tenant specific middleware.
     */
    protected function mapTenantRoutes(): void
    {
        $routeFile = __DIR__.'/../../routes/tenant.php';

        if (file_exists($routeFile)) {
            Route::middleware([
                InitializeTenancyByRequestData::class
            ])->group(function () use ($routeFile) {
                require $routeFile;
            });
        }
    }
}