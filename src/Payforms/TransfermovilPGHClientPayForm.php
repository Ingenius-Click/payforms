<?php

namespace Ingenius\Payforms\Payforms;

use Ingenius\Payforms\Features\TransfermovilPayformFeature;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

class TransfermovilPGHClientPayForm extends AbstractPaymentGatewayHubClientPayForm {

    protected string $id = 'transfermovil-pgh-client';

    protected string $name = 'Transfermovil';

    protected string $description = 'Pago por Transfermovil';

    public function getRequiredFeature(): \Ingenius\Core\Interfaces\FeatureInterface {
        return new TransfermovilPayformFeature();
    }

    protected function buildPaymentPayload(PaymentTransaction $transaction, $payable): array {

        $phone = $transaction->metadata['customer']['phone'] ?? null;

        return [
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'reference' => $transaction->reference,
            'payform_id' => 'transfermovil',
            'urlCallback' => route('payform.commit', [
                'payform' => $this->getId(),
                'tenant' => tenant()->domains()->first()?->domain
            ]),
            'data' => [
                'phone' => trim($phone, '+ '),
            ]
        ];
    }

    protected function processPaymentResponse(array $responseData, PaymentTransaction $transaction): PaymentResponse
    {
        $inner = $responseData['data'] ?? [];
        $type = $inner['type'] ?? PaymentResponse::TYPE_NONE;

        if ($type === PaymentResponse::TYPE_QR) {
            $data = $inner['data'] ?? [];
            $content = $data['content'] ?? '';
            $parsed = json_decode($content, true) ?: [];

            $href = "transfermovil://tm_compra_en_linea/action"
                . "?id_transaccion=" . ($parsed['id_transaccion'] ?? '')
                . "&importe=" . ($parsed['importe'] ?? '')
                . "&moneda=" . ($parsed['moneda'] ?? '')
                . "&numero_proveedor=" . ($parsed['numero_proveedor'] ?? '');

            return PaymentResponse::qr(
                $transaction,
                $content,
                $inner['message'] ?? null,
                array_diff_key($data, ['content' => '']) + ['href' => $href]
            );
        }

        return parent::processPaymentResponse($responseData, $transaction);
    }
}
