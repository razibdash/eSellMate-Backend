<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function createOrderBkashPayment(Payment $payment, string $callbackUrl, string $payerReference, string $invoiceNumber): Payment
    {
        $response = $this->createBkashCheckout($payment->amount, $payerReference, $callbackUrl, $invoiceNumber);

        $payment->update([
            'provider_payment_id' => $response['paymentID'] ?? null,
            'checkout_url' => $response['bkashURL'] ?? null,
            'gateway_response' => $response,
        ]);

        return $payment->fresh();
    }

    public function createBkashPayment(SubscriptionPayment $payment): SubscriptionPayment
    {
        $body = $this->createBkashCheckout(
            (float) $payment->amount,
            $payment->payer_reference,
            url('/api/payments/bkash/callback'),
            $payment->merchant_invoice_number,
        );

        $payment->update([
            'provider_payment_id' => $body['paymentID'] ?? null,
            'checkout_url' => $body['bkashURL'] ?? null,
            'gateway_response' => $body,
        ]);

        return $payment->fresh();
    }

    public function executeBkashPayment(string $paymentId): array
    {
        $token = $this->bkashToken();
        $response = Http::withHeaders($this->bkashAuthHeaders($token))
            ->acceptJson()
            ->post(rtrim(config('shopbot.gateways.bkash.base_url'), '/').'/tokenized/checkout/execute', [
                'paymentID' => $paymentId,
            ]);

        if (!$response->successful()) {
            return ['success' => false, 'data' => $response->json() ?: []];
        }

        $data = $response->json();
        return [
            'success' => ($data['transactionStatus'] ?? null) === 'Completed',
            'transaction_id' => $data['trxID'] ?? null,
            'data' => $data,
        ];
    }

    public function createNagadPayment(SubscriptionPayment $payment): SubscriptionPayment
    {
        $callback = url('/api/payments/nagad/callback');
        $response = Http::acceptJson()
            ->post(rtrim(config('shopbot.gateways.nagad.base_url'), '/').'/api/dfs/check-out/initialize/'.config('shopbot.gateways.nagad.merchant_id').'/'.$payment->merchant_invoice_number, [
                'accountNumber' => config('shopbot.gateways.nagad.merchant_number'),
                'dateTime' => now()->format('YmdHis'),
                'sensitiveData' => [
                    'merchantId' => config('shopbot.gateways.nagad.merchant_id'),
                    'orderId' => $payment->merchant_invoice_number,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'currencyCode' => '050',
                    'challenge' => Str::random(40),
                ],
                'signature' => null,
                'merchantCallbackURL' => $callback,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Nagad payment initiation failed.');
        }

        $body = $response->json();
        $payment->update([
            'provider_payment_id' => $body['paymentReferenceId'] ?? null,
            'checkout_url' => $body['callBackUrl'] ?? $body['checkout_url'] ?? null,
            'gateway_response' => $body,
        ]);

        return $payment->fresh();
    }

    public function verifyNagadPayment(string $paymentRefId): array
    {
        $response = Http::acceptJson()
            ->get(rtrim(config('shopbot.gateways.nagad.base_url'), '/').'/api/dfs/verify/payment/'.$paymentRefId);

        if (!$response->successful()) {
            return ['success' => false, 'data' => $response->json() ?: []];
        }

        $data = $response->json();
        return [
            'success' => in_array($data['status'] ?? null, ['Success', 'SUCCESS', 'Completed'], true),
            'transaction_id' => $data['issuerPaymentRefNo'] ?? $data['paymentRefId'] ?? null,
            'data' => $data,
        ];
    }

    private function bkashToken(): string
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'username' => config('shopbot.gateways.bkash.username'),
                'password' => config('shopbot.gateways.bkash.password'),
            ])
            ->post(rtrim(config('shopbot.gateways.bkash.base_url'), '/').'/tokenized/checkout/token/grant', [
                'app_key' => config('shopbot.gateways.bkash.app_key'),
                'app_secret' => config('shopbot.gateways.bkash.app_secret'),
            ]);

        if (!$response->successful() || !$response->json('id_token')) {
            throw new RuntimeException($this->gatewayErrorMessage($response->json(), 'Unable to authenticate with bKash.'));
        }

        return $response->json('id_token');
    }

    private function bkashAuthHeaders(string $token): array
    {
        return [
            'Authorization' => $token,
            'X-APP-Key' => config('shopbot.gateways.bkash.app_key'),
        ];
    }

    private function createBkashCheckout(float $amount, string $payerReference, string $callbackUrl, string $invoiceNumber): array
    {
        $token = $this->bkashToken();
        $response = Http::withHeaders($this->bkashAuthHeaders($token))
            ->acceptJson()
            ->post(rtrim(config('shopbot.gateways.bkash.base_url'), '/').'/tokenized/checkout/create', [
                'mode' => '0011',
                'payerReference' => $payerReference,
                'callbackURL' => $callbackUrl,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $invoiceNumber,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException($this->gatewayErrorMessage($response->json(), 'bKash payment initiation failed.'));
        }

        $body = $response->json();
        if (empty($body['paymentID']) || empty($body['bkashURL'])) {
            throw new RuntimeException($this->gatewayErrorMessage($body, 'bKash payment initiation response was incomplete.'));
        }

        return $body;
    }

    private function gatewayErrorMessage(mixed $body, string $fallback): string
    {
        if (!is_array($body)) {
            return $fallback;
        }

        return $body['errorMessage']
            ?? $body['statusMessage']
            ?? $body['message']
            ?? $fallback;
    }
}
