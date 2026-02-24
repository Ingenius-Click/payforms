<?php

namespace Ingenius\Payforms\Payforms;

use Illuminate\Support\Facades\Log;
use Ingenius\Core\Interfaces\IOrderable;
use Ingenius\Payforms\Features\EnzonaPayformFeature;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

class EnzonaPGHClientPayForm extends AbstractPaymentGatewayHubClientPayForm {

    protected string $id = 'enzona-pgh-client';

    protected string $name = 'Enzona';

    protected string $description = 'Pago por enzona';

    public function getRequiredFeature(): \Ingenius\Core\Interfaces\FeatureInterface {
        return new EnzonaPayformFeature();
    }

    protected function buildPaymentPayload(PaymentTransaction $transaction, $payable): array {

        $tenantDomain = tenant()->domains()->first()?->domain ?? 'http://localhost';
        $baseUrl = request()->headers->get('origin') ? request()->headers->get('origin') : $tenantDomain;

        return [
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'reference' => $transaction->reference,
            'payform_id' => 'enzona',
            'urlCallback' => route('payform.commit', [
                'payform' => $this->getId(),
                'tenant' => tenant()->domains()->first()?->domain
            ]),
            'data' => [
                'urlSuccess' => $baseUrl . '/payment-success?order=' . ($payable->id ?? ''),
                'urlFailed' => $baseUrl . '/payment-failed',
                'description' => $this->getDescription(),
                'shipping' => $payable instanceof IOrderable ? $payable->getShippingCost() : 0,
                'items' => $payable instanceof IOrderable ? collect($payable->getItems())->map(function ($item) {
                    return [
                        'name' => $item['productible_name'] ?? 'Item',
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => ($item['base_price_per_unit_in_cents'] ?? 0) / 100,
                        'description' => $item['productible_name'] ?? 'Item Desc.',
                        'tax' => (float) number_format(0, 2, '.', '')
                    ];
                })->toArray() : [],
            ]
        ];
    }
}