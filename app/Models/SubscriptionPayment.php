<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
