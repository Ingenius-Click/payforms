<?php

namespace Ingenius\Payforms\Payforms;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ingenius\Payforms\Features\TransfermovilPayformFeature;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Models\PaymentTransactionStatus;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

class TransfermovilPayForm extends AbstractPayForm 
{
    protected string $id = 'transfermovil';

    protected string $name = 'Transfermovil';

    protected string $description = 'Pago por Transfermovil';


    public function getRequiredFeature(): \Ingenius\Core\Interfaces\FeatureInterface {
        return new TransfermovilPayformFeature();
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function rules(): array
    {
        return [
            'source' => ['required','numeric'],
            'username' => ['required', 'string'],
            'clientID' => ['required', 'numeric'],
            'clientSecret' => ['required', 'string'],
            'url' => ['required', 'url']
        ];
    }

    protected function getFieldLabels(): array
    {
        return [
            'source' => __('Source Account'),
            'username' => __('Username'),
            'clientID' => __('Client ID'),
            'clientSecret' => __('Client Secret'),
            'url' => __('API URL'),
        ];
    }

    /**
     * Get a valid access token, refreshing if necessary
     *
     * @return string
     * @throws Exception
     */
    protected function getAccessToken(): string
    {
        // Check if we have a valid cached token
        $token = $this->getArg('access_token');
        $expiresAt = $this->getArg('token_expires_at');

        if ($token && $expiresAt && now()->isBefore($expiresAt)) {
            return $token;
        }

        // Token is missing or expired, get a new one
        return $this->refreshAccessToken();
    }

    /**
     * Invalidate the cached token
     */
    protected function invalidateToken(): void
    {
        $this->setArg('access_token', null);
        $this->setArg('token_expires_at', null);
    }

    /**
     * Refresh the access token from the OAuth server
     *
     * @return string
     * @throws Exception
     */
    protected function refreshAccessToken(): string
    {
        $client = new Client();

        try {
            $response = $client->request('POST', $this->getArg('url') . '/oauth/token', [
                'json' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->getArg('clientID'),
                    'client_secret' => $this->getArg('clientSecret'),
                    'scope' => '*',
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            // Store token with 5-minute buffer to prevent edge cases
            $this->setArg('access_token', $data['access_token']);
            $this->setArg('token_type', $data['token_type'] ?? 'Bearer');
            $this->setArg('token_expires_at', now()->addSeconds($data['expires_in'] - 300));

            return $data['access_token'];

        } catch (Exception $e) {
            Log::error('Transfermovil token refresh error: ' . $e->getMessage());
            throw new Exception('Failed to obtain access token: ' . $e->getMessage());
        }
    }

    /**
     * Make payment request with automatic token refresh on auth failure
     *
     * @param Client $client
     * @param string $url
     * @param array $payload
     * @param bool $isRetry
     * @return array
     * @throws Exception
     */
    protected function makePaymentRequest(Client $client, string $url, array $payload, bool $isRetry = false): array
    {
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Accept' => 'application/json'
                ],
                'json' => $payload
            ]);

            return json_decode($response->getBody(), true);

        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            // If auth error and not already retrying, invalidate token and retry once
            if (in_array($statusCode, [401, 403]) && !$isRetry) {
                Log::warning('Transfermovil auth error (token may be revoked), refreshing token and retrying');

                $this->invalidateToken();
                return $this->makePaymentRequest($client, $url, $payload, true);
            }

            // Re-throw if not auth error or already retried
            throw $e;
        }
    }

    protected function handleCreateTransaction(PaymentTransaction $transaction, $payable = null): PaymentResponse
    {

        $client = new Client();

        $source = $this->getArg('source');
        $username = $this->getArg('username');
        $url = $this->getArg('url');

        $reference = $transaction->reference;

        $payload = [
            'amount' => $transaction->amount * 100,
            'currency' => $transaction->currency ?? 'USD',
            'description' => $this->getDescription(),
            'phone' => $transaction->metadata['customer']['phone'] ?? '',
            'validTime' => 0,
            'source' => $source,
            'username' => $username,
            'externalID' => $reference,
            'callback' => route('payform.commit', [
                'payform' => $this->getId()
            ])
        ];

        try {
            $decoded = $this->makePaymentRequest($client, $url, $payload);

            $qrImage = base64_decode($decoded['data']['qrImage']);

            return PaymentResponse::qr($transaction, $qrImage);

        } catch (Exception $e) {
            Log::info('Transfermovil handleCreateTransaction error');
            Log::error($e->getMessage());

            throw $e;
        }
    }

    protected function handleCommitPayment(Request $request): PaymentTransactionStatus|null
    {
        return null;
    }
}