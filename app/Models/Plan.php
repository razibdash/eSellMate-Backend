<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $guarded = [];
    protected $casts = ['price_monthly' => 'decimal:2', 'price_yearly' => 'decimal:2', 'features_json' => 'array'];
}
