<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot; class BusinessUser extends Pivot { protected $table='business_users'; public $incrementing=true; protected $guarded=[]; protected $casts=['joined_at'=>'datetime']; public function role(){return $this->belongsTo(Role::class);} public function user(){return $this->belongsTo(User::class);} public function business(){return $this->belongsTo(Business::class);} }
