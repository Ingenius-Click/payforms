<?php

namespace Ingenius\Payforms\Actions;

use Ingenius\Core\Interfaces\IWithPayment;
use Ingenius\Payforms\Enums\PaymentStatus;
use Ingenius\Payforms\Models\PaymentTransaction;

class ManualStatusChangeAction
{
    public function handle(int $transaction_id, PaymentStatus $status)
    {
        $transaction = PaymentTransaction::findOrFail($transaction_id);
        $transaction->setStatus($status);
        $transaction->save();

        if ($status == PaymentStatus::APPROVED) {
            $payable = $transaction->payable;
            if ($payable && $payable instanceof IWithPayment) {
                $paidOrderStatusClass = config('payforms.paid_order_status_class');
                $paidOrderStatus = new $paidOrderStatusClass();
                $payable->onPaymentSuccess($paidOrderStatus->getIdentifier());
            }
        }

        if ($status == PaymentStatus::REJECTED || $status == PaymentStatus::CANCELED) {
            $payable = $transaction->payable;
            if ($payable && $payable instanceof IWithPayment) {
                $payable->onPaymentFailed();
            }
        }

        return $transaction;
    }
}
