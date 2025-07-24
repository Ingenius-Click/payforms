<?php

namespace Ingenius\Payforms\Providers;

use Illuminate\Support\ServiceProvider;
use Ingenius\Core\Support\PermissionsManager;
use Ingenius\Core\Traits\RegistersConfigurations;
use Ingenius\Payforms\Constants\PayformPermissions;
use Ingenius\Payforms\Constants\PaymentTransitionPermissions;

class PermissionServiceProvider extends ServiceProvider
{
    use RegistersConfigurations;

    /**
     * The package name.
     *
     * @var string
     */
    protected string $packageName = 'PayForm';

    /**
     * Boot the application events.
     */
    public function boot(PermissionsManager $permissionsManager): void
    {
        $this->registerPermissions($permissionsManager);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Register package-specific permission config
        $configPath = __DIR__ . '/../../config/permission.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'payforms.permission');
            $this->registerConfig($configPath, 'payforms.permission', 'payforms');
        }
    }

    /**
     * Register the package's permissions.
     */
    protected function registerPermissions(PermissionsManager $permissionsManager): void
    {
        // Register PayForm package permissions
        $permissionsManager->registerMany([
            PayformPermissions::UPDATE_PAYFORM => 'Update payment form',
        ], $this->packageName, 'tenant');

        $permissionsManager->registerMany([
            PaymentTransitionPermissions::MANUAL_STATUS_CHANGE => 'Change payment status manually',
        ], $this->packageName, 'tenant');
    }
}
