<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; class MessageTemplate extends Model { protected $guarded=[]; protected $casts=['variables_json'=>'array']; }
