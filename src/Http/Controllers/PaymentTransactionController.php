<?php

namespace Ingenius\Payforms\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Core\Http\Controllers\Controller;
use Ingenius\Payforms\Actions\ManualStatusChangeAction;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Http\Requests\ManualStatusChangeRequest;
use Ingenius\Payforms\Models\PaymentTransaction;

class PaymentTransactionController extends Controller
{
    use AuthorizesRequests;

    public function manualStatusChange(ManualStatusChangeRequest $request, PaymentTransaction $transaction, ManualStatusChangeAction $action)
    {
        $user = AuthHelper::getUser();

        $this->authorizeForUser($user, 'changeStatus', $transaction);

        $status = $request->validated()['status'];

        try {
            $transaction = $action->handle($transaction->id, PaymentStatus::from($status));
        } catch (\Exception $e) {
            return Response::api(
                'Error changing transaction status',
                data: $e->getMessage(),
                code: 500
            );
        }

        return Response::api(
            'Transaction status changed successfully',
            data: $transaction,
        );
    }
}
