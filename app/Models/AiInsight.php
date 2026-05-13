<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; class AiInsight extends Model { protected $guarded=[]; protected $casts=['data_json'=>'array','is_read'=>'boolean']; }
