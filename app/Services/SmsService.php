<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Order;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    public function sendOrderConfirmation(Order $order): SmsLog
    {
        $message = sprintf(
            'Hi %s, your order %s has been placed. Total amount: %s. Thank you for shopping with us.',
            $order->customer_name_snapshot ?: 'there',
            $order->invoice_number ?: $order->order_number,
            number_format((float) $order->total_amount, 2)
        );

        return $this->queue($order, $message, 'confirmation');
    }

    public function sendDeliveryUpdate(Order $order, string $status): SmsLog
    {
        $message = sprintf(
            'Hi %s, your order %s delivery status is now: %s.',
            $order->customer_name_snapshot ?: 'there',
            $order->invoice_number ?: $order->order_number,
            str_replace('_', ' ', $status)
        );

        return $this->queue($order, $message, 'delivery');
    }

    public function resend(SmsLog $log): SmsLog
    {
        $log->update(['status' => 'pending']);
        SendSmsJob::dispatch($log->id);

        return $log;
    }

    public function deliver(SmsLog $log): void
    {
        $log->increment('attempts');

        $phone = $this->normalizePhone($log->phone);
        if (!$phone) {
            $log->update(['status' => 'failed', 'provider_response' => 'Missing or invalid phone number']);
            return;
        }

        $config = config('shopbot.sms.sslwireless');

        try {
            $response = Http::asForm()->post(rtrim($config['base_url'], '/') . '/api/v3/send-sms', [
                'api_token' => $config['api_token'],
                'sid' => $config['sid'],
                'msisdn' => $phone,
                'sms' => $log->message,
                'csms_id' => (string) $log->id,
            ]);

            if ($response->successful() && data_get($response->json(), 'status') === 'SUCCESS') {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'provider_response' => $response->body(),
                ]);
                return;
            }

            $log->update(['status' => 'failed', 'provider_response' => $response->body()]);
            throw new RuntimeException('SMS provider responded with failure: ' . $response->body());
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'provider_response' => $log->provider_response ?: $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function queue(Order $order, string $message, string $type): SmsLog
    {
        $log = SmsLog::create([
            'business_id' => $order->business_id,
            'order_id' => $order->id,
            'phone' => (string) $order->customer_phone_snapshot,
            'message' => $message,
            'type' => $type,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        SendSmsJob::dispatch($log->id);

        return $log;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '880' . substr($digits, 1);
        }

        return '880' . $digits;
    }
}
