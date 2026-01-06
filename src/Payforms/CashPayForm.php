<?php

namespace Ingenius\Payforms\Payforms;

use Illuminate\Http\Request;
use Ingenius\Core\Interfaces\FeatureInterface;
use Ingenius\Payforms\Features\CashPayformFeature;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Models\PaymentTransactionStatus;
use Ingenius\Payforms\Payforms\AbstractPayForm;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

class CashPayForm extends AbstractPayForm
{
    protected string $id = 'cash';
    protected string $name = 'Efectivo';
    protected string $description = 'Pago en efectivo';

    public function getRequiredFeature(): FeatureInterface
    {
        return new CashPayformFeature();
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function rules(): array
    {
        // Cash payment doesn't need any specific validation rules
        return [];
    }

    protected function handleCreateTransaction(PaymentTransaction $transaction, $payable = null): PaymentResponse
    {
        return PaymentResponse::none($transaction);
    }

    protected function handleCommitPayment(Request $request): PaymentTransactionStatus|null
    {
        return null;
    }
}
