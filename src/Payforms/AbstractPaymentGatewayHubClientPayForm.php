<?php

namespace Ingenius\Payforms\Payforms;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Ingenius\Payforms\Models\PaymentTransaction;
use Ingenius\Payforms\Payforms\Responses\PaymentResponse;

/**
 * Abstract Payment Gateway Hub PayForm
 *
 * This abstract class provides reusable logic for PayForms that connect to a centralized
 * payment gateway hub service. The hub acts as a Sanctum client and provides a unified
 * payment interface where implementations only need to:
 * 1. Authenticate once (handled by this class)
 * 2. Make payment requests to a standard endpoint (handled by this class)
 * 3. Handle the gateway-specific response format (implemented by child classes)
 *
 * The class manages OAuth 2.0 Client Credentials authentication, token caching,
 * automatic token refresh, and standardized payment request handling.
 *
 * @author Claude Code
 */
abstract class AbstractPaymentGatewayHubClientPayForm extends AbstractPayForm
{
    /**
     * The payment endpoint path on the gateway hub
     * Default: /api/payments
     * Can be overridden by child classes if needed
     */
    protected string $paymentEndpoint = '/payments';

    /**
     * The OAuth token endpoint path
     * Default: /oauth/token
     */
    protected string $tokenEndpoint = '/apps/token';

    /**
     * Number of seconds before token expiration to trigger refresh
     * Default: 300 seconds (5 minutes)
     */
    protected int $tokenRefreshBuffer = 300;

    /**
     * Get the base validation rules for gateway hub configuration
     * Child classes should merge these with their own specific rules
     *
     * @return array
     */
    protected function getBaseRules(): array
    {
        return [
            'url' => ['required', 'url'],
            'clientID' => ['required', 'numeric'],
            'clientSecret' => ['required', 'string'],
        ];
    }

    /**
     * Get validation rules for this payform
     * Child classes must implement this to add any additional required fields
     *
     * @return array
     */
    public function rules(): array {
        return $this->getBaseRules();
    }

    /**
     * Get the access token, refreshing if necessary
     * Handles token caching and automatic refresh before expiration
     *
     * @return string The valid access token
     * @throws \Exception If token refresh fails
     */
    protected function getAccessToken(): string
    {
        $token = $this->getArg('access_token');

        if (!$token) {
            $token = $this->refreshAccessToken();
        }

        return $token;
    }

    /**
     * Refresh the OAuth access token from the gateway hub
     * Uses OAuth 2.0 Client Credentials flow
     *
     * @return string The new access token
     * @throws \Exception If authentication fails
     */
    protected function refreshAccessToken(): string
    {
        $client = new Client();

        try {
            $response = $client->request('POST', $this->getArg('url') . $this->tokenEndpoint, [
                'json' => [
                    'app_id' => $this->getArg('clientID'),
                    'app_secret' => $this->getArg('clientSecret')
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            $data = $data['data'] ?? [];

            if(!isset($data['access_token'])) {
                throw new \Exception("Invalid token response from payment gateway hub.");
            }

            // Cache the token with a buffer before expiration
            $this->setArg('access_token', $data['access_token']);
            $this->setArg('token_type', $data['token_type'] ?? 'Bearer');

            Log::info("Payment gateway hub token refreshed for payform: {$this->id}");

            return $data['access_token'];
        } catch (GuzzleException $e) {
            Log::error("Failed to refresh payment gateway hub token for payform {$this->id}: " . $e->getMessage());
            throw new \Exception("Failed to authenticate with payment gateway hub: " . $e->getMessage());
        }
    }

    /**
     * Invalidate the cached access token
     * Called when authentication fails to force token refresh on next request
     *
     * @return void
     */
    protected function invalidateToken(): void
    {
        $this->setArg('access_token', null);
        Log::warning("Payment gateway hub token invalidated for payform: {$this->id}");
    }

    /**
     * Make an authenticated payment request to the gateway hub
     * Handles authentication, automatic token refresh, and retry on auth failure
     *
     * @param Client $client The Guzzle HTTP client
     * @param string $url The full request URL
     * @param array $payload The request payload
     * @param bool $isRetry Whether this is a retry attempt after token refresh
     * @return array The decoded response data
     * @throws \Exception If the request fails after retry
     */
    protected function makePaymentRequest(Client $client, string $url, array $payload, bool $isRetry = false): array
    {
        try {
            $response = $client->request('POST', $url, [
                'body' => json_encode($payload, JSON_PRESERVE_ZERO_FRACTION),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            // If authentication failed and this is not a retry, refresh token and retry once
            if (($statusCode === 401 || $statusCode === 403) && !$isRetry) {
                Log::warning("Payment gateway hub authentication failed, refreshing token for payform: {$this->id}");
                $this->invalidateToken();
                $this->getAccessToken(); // This will refresh the token

                // Retry the request once with the new token
                return $this->makePaymentRequest($client, $url, $payload, true);
            }

            // If it's already a retry or a different error, throw exception
            $errorMessage = $e->getResponse()->getBody()->getContents();
            Log::error("Payment gateway hub request failed for payform {$this->id}: {$errorMessage}");
            throw new \Exception("Payment request failed: {$errorMessage}");
        } catch (GuzzleException $e) {
            Log::error("Payment gateway hub request error for payform {$this->id}: " . $e->getMessage());
            throw new \Exception("Payment request error: " . $e->getMessage());
        }
    }

    /**
     * Build the payment request payload
     * Child classes must implement this to construct the gateway-specific payload
     *
     * @param \Ingenius\Payforms\Models\PaymentTransaction $transaction The payment transaction
     * @param mixed $payable The payable model (e.g., Order)
     * @return array The payment request payload
     */
    abstract protected function buildPaymentPayload(PaymentTransaction $transaction, $payable): array;

    /**
     * Process the payment response from the gateway hub
     * Maps the hub response type to the appropriate PaymentResponse
     *
     * @param array $responseData The response data from the gateway hub
     * @param \Ingenius\Payforms\Models\PaymentTransaction $transaction The payment transaction
     * @return \Ingenius\Payforms\Payforms\Responses\PaymentResponse
     */
    protected function processPaymentResponse(array $responseData, PaymentTransaction $transaction): PaymentResponse
    {
        $responseData = $responseData['data'] ?? [];

        $type = $responseData['type'] ?? PaymentResponse::TYPE_NONE;
        $data = $responseData['data'] ?? [];
        $message = $responseData['message'] ?? null;

        return match ($type) {
            PaymentResponse::TYPE_REDIRECT => PaymentResponse::redirect(
                $transaction,
                $data['url'] ?? '',
                $message,
                array_diff_key($data, ['url' => ''])
            ),
            PaymentResponse::TYPE_QR => PaymentResponse::qr(
                $transaction,
                $data['content'] ?? '',
                $message,
                array_diff_key($data, ['content' => ''])
            ),
            PaymentResponse::TYPE_FORM => PaymentResponse::form(
                $transaction,
                $data['fields'] ?? [],
                $message,
                array_diff_key($data, ['fields' => ''])
            ),
            PaymentResponse::TYPE_COMPONENT => PaymentResponse::component(
                $transaction,
                $data['component'] ?? '',
                $data['props'] ?? [],
                $message,
                array_diff_key($data, ['component' => '', 'props' => ''])
            ),
            PaymentResponse::TYPE_INFO => PaymentResponse::info(
                $transaction,
                $data['instructions'] ?? '',
                $data['email'] ?? '',
                $message,
                array_diff_key($data, ['instructions' => '', 'email' => ''])
            ),
            default => PaymentResponse::none($transaction, $message),
        };
    }

    /**
     * Handle creating a transaction with the payment gateway hub
     * This method orchestrates the payment flow:
     * 1. Builds the payment payload (gateway-specific)
     * 2. Makes authenticated request to the gateway hub
     * 3. Processes the response (gateway-specific)
     *
     * @param \Ingenius\Payforms\Models\PaymentTransaction $transaction
     * @param mixed $payable
     * @return \Ingenius\Payforms\Payforms\Responses\PaymentResponse
     * @throws \Exception If payment creation fails
     */
    protected function handleCreateTransaction(PaymentTransaction $transaction, $payable = null): PaymentResponse
    {
        $client = new Client();
        $url = $this->getArg('url') . $this->paymentEndpoint;

        // Build the payment payload (implemented by child class)
        $payload = $this->buildPaymentPayload($transaction, $payable);

        // Make authenticated request to gateway hub
        $responseData = $this->makePaymentRequest($client, $url, $payload);

        // Process the response and return appropriate PaymentResponse (implemented by child class)
        return $this->processPaymentResponse($responseData, $transaction);
    }

    /**
     * Get configuration fields schema for the admin UI
     * Combines base fields with child-specific fields
     *
     * @return array
     */
    public function getFieldsConfig(): array
    {
        $baseFields = [
            'url' => [
                'type' => 'url',
                'label' => __('Gateway Hub URL'),
                'placeholder' => 'https://hub.example.com',
                'rules' => $this->rules()['url'],
                'group' => __('Connection'),
                'order' => 1,
            ],
            'clientID' => [
                'type' => 'number',
                'label' => __('Client ID'),
                'rules' => $this->rules()['clientID'],
                'group' => __('Credentials'),
                'order' => 2,
            ],
            'clientSecret' => [
                'type' => 'text',
                'label' => __('Client Secret'),
                'rules' => $this->rules()['clientSecret'],
                'group' => __('Credentials'),
                'order' => 2,
            ]
        ];

        // Child classes can override this method to add additional fields
        return $baseFields;
    }

    /**
     * Handle webhook payment commit from the payment gateway hub
     * Verifies HMAC signature and processes the payment status update
     *
     * @param \Illuminate\Http\Request $request The webhook request
     * @return \Ingenius\Payforms\Models\PaymentTransactionStatus|null
     * @throws \Exception If signature verification fails
     */
    protected function handleCommitPayment(\Illuminate\Http\Request $request): \Ingenius\Payforms\Models\PaymentTransactionStatus|null {

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            Log::error("Invalid webhook signature for payform: {$this->id}");
            throw new \Exception('Invalid webhook signature');
        }

        // Extract transaction data from request
        $transactionId = $request->input('reference');
        $status = $request->input('status');

        if (!$transactionId) {
            Log::error('Missing transaction_id in webhook request');
            throw new \Exception('Missing transaction_id');
        }

        // Find the transaction
        $transaction = PaymentTransaction::where('reference', $transactionId)->first();

        if (!$transaction) {
            Log::error("Transaction not found: {$transactionId}");
            throw new \Exception("Transaction not found: {$transactionId}");
        }

        // Only process if status is 'paid'
        if ($status === 'paid') {
            Log::info("Processing payment for transaction: {$transactionId}");

            // Mark transaction as paid
            return $transaction->pay();
        }

        Log::warning("Webhook received for transaction {$transactionId} with status: {$status}");
        return null;
    }

    /**
     * Verify the HMAC signature of the webhook request
     * Uses the client secret to verify the signature in the x-webhook-signature header
     *
     * @param \Illuminate\Http\Request $request The webhook request
     * @return bool True if signature is valid, false otherwise
     */
    protected function verifyWebhookSignature(\Illuminate\Http\Request $request): bool
    {
        $signatureHeader = $request->header('x-webhook-signature');

        if (!$signatureHeader) {
            Log::warning('Missing x-webhook-signature header');
            return false;
        }

        // Parse the signature header: t=timestamp,v1=signature
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $keyValue = explode('=', $part, 2);
            if (\count($keyValue) === 2) {
                $parts[$keyValue[0]] = $keyValue[1];
            }
        }

        if (!isset($parts['t']) || !isset($parts['v1'])) {
            Log::warning('Invalid signature header format');
            return false;
        }

        $timestamp = $parts['t'];
        $expectedSignature = $parts['v1'];

        // Construct the signed payload: timestamp.json_body
        $payload = "{$timestamp}.{$request->getContent()}";

        // Calculate HMAC signature using client secret
        $clientSecret = $this->getArg('clientSecret');
        $calculatedSignature = hash_hmac('sha256', $payload, $clientSecret);

        // Constant-time comparison to prevent timing attacks
        $isValid = hash_equals($calculatedSignature, $expectedSignature);

        if (!$isValid) {
            Log::warning("Signature mismatch - Expected: {$expectedSignature}, Calculated: {$calculatedSignature}");
        }

        return $isValid;
    }
}
