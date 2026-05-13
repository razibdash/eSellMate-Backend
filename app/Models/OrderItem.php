<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; class OrderItem extends Model { protected $guarded=[]; protected $casts=['unit_price'=>'decimal:2','discount_amount'=>'decimal:2','line_total'=>'decimal:2']; public function product(){return $this->belongsTo(Product::class);} }
