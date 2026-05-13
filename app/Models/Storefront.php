<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Storefront extends Model
{
    protected $guarded = [];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'social_links' => 'array',
        'settings_json' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
