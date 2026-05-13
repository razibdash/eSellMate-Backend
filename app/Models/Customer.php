<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\SoftDeletes; class Customer extends Model { use SoftDeletes; protected $guarded=[]; protected $casts=['total_spent'=>'decimal:2','last_order_at'=>'datetime']; public function orders(){return $this->hasMany(Order::class);} public function addresses(){return $this->hasMany(CustomerAddress::class);} }
