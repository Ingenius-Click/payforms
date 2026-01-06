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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Ingenius\Payforms\Http\Controllers\PayFormDataController;
use Ingenius\Payforms\Http\Controllers\PaymentTransactionController;
use Ingenius\Payforms\Services\PayformsManager;

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
            Route::get('/{payFormData}', [PayFormDataController::class, 'show'])
                ->name('payforms.show')
                ->middleware('tenant.has.feature:update-payforms')
            ;
            Route::get('/', [PayFormDataController::class, 'index'])
                ->name('payforms.index')
                ->middleware('tenant.has.feature:list-payforms');
            Route::put('/{payFormData}', [PayFormDataController::class, 'update'])
                ->name('payforms.update')
                ->middleware('tenant.has.feature:update-payforms');
        });

        Route::post('/{payform}/commit', function(Request $request, $payform, PayformsManager $payformsManager) {
            $payformInstance = $payformsManager->getPayform($payform);

            if(!$payformInstance) {
                abort(404, __('No payform found or active'));
            }

            try {
                $payformInstance->commitPayment($request);
            } catch (Exception $e) {
                Log::error($e->getMessage());
                return Response::api(__('An error occurred'), 500);
            }

            return Response::api(__('Payment commited successfully'));
        })->name('payform.commit');
    });
});

// Route::get('tenant-example', function () {
//     return 'Hello from tenant-specific route! Current tenant: ' . tenant('id');
// });