<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; class Invoice extends Model { protected $guarded=[]; protected $casts=['invoice_data_json'=>'array','generated_at'=>'datetime']; }
