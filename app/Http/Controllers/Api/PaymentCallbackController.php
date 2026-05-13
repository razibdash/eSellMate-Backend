<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\SubscriptionPayment;
use App\Models\Storefront;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class PaymentCallbackController extends ApiController
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly SubscriptionService $subscriptions,
        private readonly OrderService $orders,
    ) {}

    public function bkash(Request $request)
    {
        $status = $request->query('status') ?: $request->input('status');
        $paymentId = $request->query('paymentID') ?: $request->input('paymentID');
        $frontend = rtrim(config('shopbot.frontend_url'), '/');
        $storefrontFrontend = rtrim(config('shopbot.storefront.frontend_base_url'), '/');

        if ($status !== 'success' || !$paymentId) {
            return $this->resolveBkashFailureRedirect($paymentId, $storefrontFrontend, $frontend);
        }

        $orderPayment = Payment::where('provider_payment_id', $paymentId)->with('order')->first();
        if ($orderPayment) {
            $verified = $this->payments->executeBkashPayment($paymentId);

            if (!$verified['success']) {
                $this->orders->markPaymentFailed($orderPayment, 'failed', $verified['data'] ?? []);

                return redirect()->away($this->storefrontPaymentUrl($orderPayment->order?->public_reference, $storefrontFrontend, 'failed'));
            }

            $this->orders->markPaymentSuccessful($orderPayment, $verified['transaction_id'] ?? null, $verified['data'] ?? []);

            return redirect()->away($this->storefrontPaymentUrl($orderPayment->order?->public_reference, $storefrontFrontend, 'success'));
        }

        $payment = SubscriptionPayment::where('provider_payment_id', $paymentId)->first();
        if (!$payment) {
            return redirect()->away($frontend.'/subscription/failed');
        }

        $verified = $this->payments->executeBkashPayment($paymentId);
        if (!$verified['success']) {
            $payment->update(['status' => 'failed', 'gateway_response' => $verified['data'] ?? []]);
            return redirect()->away($frontend.'/subscription/failed?payment_id='.$payment->id);
        }

        $this->subscriptions->activateFromPayment($payment, $verified['transaction_id'] ?? null, $verified['data'] ?? []);

        return redirect()->away($frontend.'/subscription/success?payment_id='.$payment->id);
    }

    public function nagad(Request $request)
    {
        $paymentRefId = $request->query('payment_ref_id')
            ?: $request->query('paymentRefId')
            ?: $request->query('paymentReferenceId')
            ?: $request->input('paymentRefId');
        $frontend = rtrim(config('shopbot.frontend_url'), '/');

        if (!$paymentRefId) {
            return redirect()->away($frontend.'/subscription/failed');
        }

        $payment = SubscriptionPayment::where('provider_payment_id', $paymentRefId)->first();
        if (!$payment) {
            return redirect()->away($frontend.'/subscription/failed');
        }

        $verified = $this->payments->verifyNagadPayment($paymentRefId);
        if (!$verified['success']) {
            $payment->update(['status' => 'failed', 'gateway_response' => $verified['data'] ?? []]);
            return redirect()->away($frontend.'/subscription/failed?payment_id='.$payment->id);
        }

        $this->subscriptions->activateFromPayment($payment, $verified['transaction_id'] ?? null, $verified['data'] ?? []);

        return redirect()->away($frontend.'/subscription/success?payment_id='.$payment->id);
    }

    private function resolveBkashFailureRedirect(?string $paymentId, string $storefrontFrontend, string $subscriptionFrontend)
    {
        if ($paymentId) {
            $payment = Payment::where('provider_payment_id', $paymentId)->with('order')->first();
            if ($payment?->order?->public_reference) {
                return redirect()->away($this->storefrontPaymentUrl($payment->order->public_reference, $storefrontFrontend, 'cancelled'));
            }
        }

        return redirect()->away($subscriptionFrontend.'/subscription/failed');
    }

    private function storefrontPaymentUrl(?string $reference, string $frontend, string $status): string
    {
        return $frontend.'/shop/payment/'.($reference ?: 'unknown').'?status='.$status;
    }
}
