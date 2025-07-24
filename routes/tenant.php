<?php

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here is where you can register tenant-specific routes for your package.
| These routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Ingenius\Payforms\Http\Controllers\PayFormDataController;
use Ingenius\Payforms\Http\Controllers\PaymentTransactionController;

Route::middleware([
    'api',
])->prefix('api')->group(function () {
    Route::middleware(['tenant.user'])->prefix('payment-transactions')->group(function () {
        Route::put('{transaction}/manual-status-change', [PaymentTransactionController::class, 'manualStatusChange'])
            ->name('payment-transactions.manual-status-change')
            ->middleware('tenant.has.feature:manual-status-change');
    });

    Route::prefix('payforms')->group(function () {
        Route::get('/actives', [PayFormDataController::class, 'actives'])
            ->name('payforms.index.actives')
        ;
        Route::middleware('tenant.user')->group(function () {
            Route::get('/', [PayFormDataController::class, 'index'])
                ->name('payforms.index')
                ->middleware('tenant.has.feature:list-payforms');
            Route::put('/', [PayFormDataController::class, 'update'])
                ->name('payforms.update')
                ->middleware('tenant.has.feature:update-payforms');
        });
    });
});

// Route::get('tenant-example', function () {
//     return 'Hello from tenant-specific route! Current tenant: ' . tenant('id');
// });