<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $smsLogId)
    {
    }

    public function handle(SmsService $sms): void
    {
        $log = SmsLog::find($this->smsLogId);
        if (!$log) {
            return;
        }

        $sms->deliver($log);
    }

    public function failed(Throwable $exception): void
    {
        SmsLog::where('id', $this->smsLogId)->update([
            'status' => 'failed',
            'provider_response' => $exception->getMessage(),
        ]);
    }
}
