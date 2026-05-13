<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Throwable;

class SubscriptionController extends ApiController
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
    ) {}

    public function plans()
    {
        return $this->ok(Plan::where('status','active')->orderBy('price_monthly')->get(), 'Plans');
    }

    public function current(Request $request)
    {
        return $this->ok($this->business($request)->load('subscription.plan')->subscription, 'Current subscription');
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required','exists:plans,id'],
            'billing_cycle' => ['required','in:monthly,yearly'],
            'payment_method' => ['required','in:bkash,nagad,bank'],
            'transaction_id' => ['nullable','required_if:payment_method,bank','string','max:150'],
            'bank_name' => ['nullable','string','max:120'],
            'bank_account_name' => ['nullable','string','max:150'],
            'bank_deposit_date' => ['nullable','string','max:40'],
            'note' => ['nullable','string','max:1000'],
        ]);

        $business = $this->business($request);
        $plan = Plan::where('status', 'active')->findOrFail($data['plan_id']);
        $method = $data['payment_method'];

        $payment = $this->subscriptions->createPaymentIntent($business, $plan, $data['billing_cycle'], $method, $data);

        if ($method === 'bank') {
            return $this->ok([
                'payment' => $payment->load('plan'),
                'bank' => config('shopbot.bank_payment'),
            ], 'Bank payment submitted for review', 201);
        }

        try {
            $payment = $method === 'bkash'
                ? $this->payments->createBkashPayment($payment)
                : $this->payments->createNagadPayment($payment);
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed', 'note' => trim(($payment->note ? $payment->note."\n" : '').$e->getMessage())]);
            return $this->fail('Payment gateway initiation failed. Please try another method.', 502);
        }

        return $this->ok(['payment' => $payment->load('plan'), 'redirect_url' => $payment->checkout_url], 'Payment initiated', 201);
    }

    public function verify(Request $request, int $id)
    {
        $payment = SubscriptionPayment::where('business_id', $this->business($request)->id)->findOrFail($id);

        if ($payment->status === 'paid' || $payment->payment_method === 'bank') {
            return $this->ok($payment->load('subscription.plan'), 'Payment status');
        }

        $verified = $payment->payment_method === 'bkash'
            ? $this->payments->executeBkashPayment((string) $payment->provider_payment_id)
            : $this->payments->verifyNagadPayment((string) $payment->provider_payment_id);

        if (!$verified['success']) {
            return $this->ok($payment->fresh()->load('subscription.plan'), 'Payment is not complete yet');
        }

        $payment = $this->subscriptions->activateFromPayment($payment, $verified['transaction_id'] ?? null, $verified['data'] ?? []);

        return $this->ok($payment, 'Subscription activated');
    }

    public function invoices(Request $request)
    {
        return $this->ok(SubscriptionPayment::where('business_id',$this->business($request)->id)->latest()->paginate($request->integer('per_page',20)), 'Subscription payment history');
    }
}
