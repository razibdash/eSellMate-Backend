<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
