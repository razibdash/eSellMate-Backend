<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function currentOrCreate(Business $business, Plan $plan, string $billingCycle): Subscription
    {
        return $business->subscription()->first()
            ?: Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => 'past_due',
                'billing_cycle' => $billingCycle,
            ]);
    }

    public function amountFor(Plan $plan, string $billingCycle): float
    {
        return (float) ($billingCycle === 'yearly' && $plan->price_yearly !== null
            ? $plan->price_yearly
            : $plan->price_monthly);
    }

    public function createPaymentIntent(Business $business, Plan $plan, string $billingCycle, string $method, array $extra = []): SubscriptionPayment
    {
        $subscription = $this->currentOrCreate($business, $plan, $billingCycle);

        return SubscriptionPayment::create([
            'business_id' => $business->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'amount' => $this->amountFor($plan, $billingCycle),
            'payment_method' => $method,
            'merchant_invoice_number' => 'SB-SUB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'status' => 'pending',
            'note' => $extra['note'] ?? null,
            'payer_reference' => $extra['payer_reference'] ?? (string) $business->id,
            'bank_name' => $extra['bank_name'] ?? null,
            'bank_account_name' => $extra['bank_account_name'] ?? null,
            'bank_deposit_date' => $extra['bank_deposit_date'] ?? null,
            'transaction_id' => $extra['transaction_id'] ?? null,
        ]);
    }

    public function activateFromPayment(SubscriptionPayment $payment, ?string $transactionId = null, array $gatewayResponse = []): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $transactionId, $gatewayResponse) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'paid') {
                return $payment->load('subscription.plan');
            }

            if ($payment->status !== 'pending') {
                return $payment->load('subscription.plan');
            }

            $subscription = Subscription::whereKey($payment->subscription_id)->lockForUpdate()->firstOrFail();
            $baseDate = $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at->copy()
                : now();
            $endsAt = $payment->billing_cycle === 'yearly'
                ? $baseDate->copy()->addYear()
                : $baseDate->copy()->addMonth();

            $subscription->update([
                'plan_id' => $payment->plan_id ?: $subscription->plan_id,
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?: now(),
                'ends_at' => $endsAt,
                'billing_cycle' => $payment->billing_cycle,
            ]);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => $transactionId ?: $payment->transaction_id,
                'gateway_response' => $gatewayResponse ?: $payment->gateway_response,
            ]);

            return $payment->fresh(['subscription.plan']);
        });
    }

    public function expirePastDueSubscriptions(): int
    {
        return Subscription::whereIn('status', ['trial', 'active', 'past_due'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
