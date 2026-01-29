<?php

namespace Ingenius\Payforms\Providers;

use Illuminate\Support\ServiceProvider;
use Ingenius\Core\Services\FeatureManager;
use Ingenius\Core\Support\TenantInitializationManager;
use Ingenius\Core\Traits\RegistersConfigurations;
use Ingenius\Core\Traits\RegistersMigrations;
use Ingenius\Orders\Services\InvoiceDataManager;
use Ingenius\Orders\Services\OrderExtensionManager;
use Ingenius\Orders\Services\OrderStatusManager;
use Ingenius\Payforms\Console\Commands\AddOrderStatusTransitionsCommand;
use Ingenius\Payforms\Extra\PayformExtensionForOrderCreation;
use Ingenius\Payforms\Features\CashPayformFeature;
use Ingenius\Payforms\Features\EnzonaPayformFeature;
use Ingenius\Payforms\Features\ListPayformsFeature;
use Ingenius\Payforms\Features\ManualStatusChangeFeature;
use Ingenius\Payforms\Features\TransfermovilPayformFeature;
use Ingenius\Payforms\Features\UpdatePayformsFeature;
use Ingenius\Payforms\Initializers\PayformsTenantInitializer;
use Ingenius\Payforms\InvoiceData\PayformInvoiceDataProvider;
use Ingenius\Payforms\NewOrderStatuses\PaidOrderStatus;
use Ingenius\Payforms\Payforms\CashPayForm;
use Ingenius\Payforms\Payforms\EnzonaPGHClientPayForm;
use Ingenius\Payforms\Payforms\TransfermovilPayForm;
use Ingenius\Payforms\Services\PayformsManager;
use Ingenius\Payforms\Policies\PayFormDataPolicy;
use Ingenius\Payforms\Policies\PaymentTransactionPolicy;
use Ingenius\Payforms\Models\PayFormData;
use Ingenius\Payforms\Models\PaymentTransaction;
use Illuminate\Support\Facades\Gate;

class PayformsServiceProvider extends ServiceProvider
{
    use RegistersMigrations, RegistersConfigurations;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/payforms.php', 'payforms');

        // Register configuration with the registry
        $this->registerConfig(__DIR__ . '/../../config/payforms.php', 'payforms', 'payforms');

        // Load translations early so they're available for permission registration
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'payforms');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');

        // Register policies
        $this->registerPolicies();

        // Check if permission config exists and register it
        $permissionConfigPath = __DIR__ . '/../../config/permission.php';
        if (file_exists($permissionConfigPath)) {
            $this->mergeConfigFrom($permissionConfigPath, 'payforms.permission');
            $this->registerConfig($permissionConfigPath, 'payforms.permission', 'payforms');
        }

        // Register the route service provider
        $this->app->register(RouteServiceProvider::class);

        // Register the permission service provider
        $this->app->register(PermissionServiceProvider::class);

        // Register the PayformsManager service
        $this->app->singleton(PayformsManager::class, function () {
            return new PayformsManager();
        });

        // Register the order extension
        $this->app->afterResolving(OrderExtensionManager::class, function (OrderExtensionManager $manager) {
            $manager->register(new PayformExtensionForOrderCreation($this->app->make(PayformsManager::class)));
        });

        // Register the invoice data provider
        $this->app->afterResolving(InvoiceDataManager::class, function (InvoiceDataManager $manager) {
            $manager->register(new PayformInvoiceDataProvider($this->app->make(PayformsManager::class)));
        });

        // Register payforms
        $this->registerPayforms();

        $this->app->afterResolving(FeatureManager::class, function (FeatureManager $manager) {
            $manager->register(new ListPayformsFeature());
            $manager->register(new UpdatePayformsFeature());
            $manager->register(new ManualStatusChangeFeature());
            $manager->register(new CashPayformFeature());
            $manager->register(new TransfermovilPayformFeature());
            $manager->register(new EnzonaPayformFeature());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register migrations with the registry
        $this->registerMigrations(__DIR__ . '/../../database/migrations', 'payforms');

        // Check if there's a tenant migrations directory and register it
        $tenantMigrationsPath = __DIR__ . '/../../database/migrations/tenant';
        if (is_dir($tenantMigrationsPath)) {
            $this->registerTenantMigrations($tenantMigrationsPath, 'payforms');
        }

        // Load views only if they exist
        $viewsPath = __DIR__ . '/../../resources/views';
        if (is_dir($viewsPath) && count(glob($viewsPath . '/*.blade.php')) > 0) {
            $this->loadViewsFrom($viewsPath, 'payforms');
            
            // Publish views only if they exist
            $this->publishes([
                $viewsPath => resource_path('views/vendor/payforms'),
            ], 'payforms-views');
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/payforms.php' => config_path('payforms.php'),
        ], 'payforms-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'payforms-migrations');

        // Register commands
        $this->registerCommands();

        // Register order statuses
        $this->registerOrderStatuses();

        // Register tenant initializer
        $this->registerTenantInitializer();
    }

    /**
     * Register translations.
     */
    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'payforms');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AddOrderStatusTransitionsCommand::class,
            ]);
        }
    }

    /**
     * Register payforms.
     */
    protected function registerPayforms(): void
    {
        $this->app->afterResolving(PayformsManager::class, function (PayformsManager $manager) {
            $manager->registerPayform('cash', CashPayForm::class);
            $manager->registerPayform('transfermovil', TransfermovilPayForm::class);
            $manager->registerPayform('enzona-pgh-client', EnzonaPGHClientPayForm::class);
        });
    }

    /**
     * Register order statuses.
     */
    protected function registerOrderStatuses(): void
    {
        $this->app->afterResolving(OrderStatusManager::class, function (OrderStatusManager $manager) {
            $manager->register(new PaidOrderStatus());
        });
    }

    /**
     * Register tenant initializer
     */
    protected function registerTenantInitializer(): void
    {
        $this->app->afterResolving(TenantInitializationManager::class, function (TenantInitializationManager $manager) {
            $initializer = $this->app->make(PayformsTenantInitializer::class);
            $manager->register($initializer);
        });
    }

    protected function registerPolicies(): void
    {
        Gate::policy(PayFormData::class, PayFormDataPolicy::class);
        Gate::policy(PaymentTransaction::class, PaymentTransactionPolicy::class);
    }
}
