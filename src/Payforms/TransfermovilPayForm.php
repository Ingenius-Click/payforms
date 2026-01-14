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
            'publicKey' => ['required', 'string'],
            'url' => ['required', 'url'],
        ];
    }

    public function getFieldsConfig(): array
    {
        return [
            'source' => [
                'label' => __('Source Account'),
                'type' => 'number',
                'rules' => $this->rules()['source'],
                'description' => __('The source account number for Transfermovil transactions'),
                'placeholder' => __('e.g., 123456789'),
                'group' => 'credentials',
                'order' => 1,
            ],
            'username' => [
                'label' => __('Username'),
                'type' => 'text',
                'rules' => $this->rules()['username'],
                'description' => __('Your Transfermovil API username'),
                'placeholder' => __('Enter your username'),
                'group' => 'credentials',
                'order' => 2,
            ],
            'clientID' => [
                'label' => __('Client ID'),
                'type' => 'number',
                'rules' => $this->rules()['clientID'],
                'description' => __('OAuth client ID provided by Transfermovil'),
                'placeholder' => __('Enter your client ID'),
                'group' => 'credentials',
                'order' => 3,
            ],
            'clientSecret' => [
                'label' => __('Client Secret'),
                'type' => 'password',
                'rules' => $this->rules()['clientSecret'],
                'description' => __('OAuth client secret (kept secure and encrypted)'),
                'placeholder' => __('Enter your client secret'),
                'group' => 'credentials',
                'order' => 4,
                'attributes' => [
                    'autocomplete' => 'off',
                ],
            ],
            'publicKey' => [
                'label' => __('Public Key'),
                'type' => 'textarea',
                'rules' => $this->rules()['publicKey'],
                'description' => __('Public key for verifying webhook signatures'),
                'placeholder' => __('Paste your public key here'),
                'group' => 'security',
                'order' => 5,
                'attributes' => [
                    'rows' => 5,
                ],
            ],
            'url' => [
                'label' => __('API URL'),
                'type' => 'url',
                'rules' => $this->rules()['url'],
                'description' => __('The base URL for Transfermovil API endpoints'),
                'placeholder' => __('https://api.transfermovil.cu'),
                'group' => 'configuration',
                'order' => 6,
            ],
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
            'amount' => $transaction->amount / 100,
            'currency' => $transaction->currency ?? 'USD',
            'description' => $this->getDescription(),
            'phone' => ltrim($transaction->metadata['customer']['phone'] ?? '', '+'),
            'validTime' => 0,
            'source' => $source,
            'username' => $username,
            'externalID' => $reference,
            'callback' => route('payform.commit', [
                'payform' => $this->getId(),
                'tenant' => tenant()->domains()->first()?->domain
            ])
        ];

        // Build the full URL with the resource endpoint
        $resource = 'api/create-payment';
        $fullUrl = rtrim($url, '/') . '/' . $resource;

        try {
            $decoded = $this->makePaymentRequest($client, $fullUrl, $payload);

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
        Log::info('Transfermovil handleCommitPayment called');

        $publicKey = $this->getArg('publicKey');
        $encryptedPayload = $request->input('encrypted') ?? [];

        //Decrypt and verify the webhook payload using the public key
        openssl_public_decrypt(base64_decode($encryptedPayload), $decryptedData, $publicKey);

        $data = json_decode($decryptedData, true);

        Log::info('Decrypted webhook payload: ' . json_encode($data));

        if (!$data) {
            Log::error('Transfermovil webhook: Failed to decrypt or parse payload');
            return null;
        }

        if(isset($data['status'])) {
            if($data['status'] == 'PAID') {
                $reference = $data['externalID'] ?? null;
                if(!$reference) {
                    Log::error('Transfermovil webhook: Missing externalID in payload');
                    return null;
                }
                $transaction = PaymentTransaction::where('reference', $reference)->first();

                if(!$transaction) {
                    Log::error('Transfermovil webhook: No transaction found for reference ' . $reference);
                    return null;
                }

                return $transaction->pay();
            }
        }

        return null;
    }
}