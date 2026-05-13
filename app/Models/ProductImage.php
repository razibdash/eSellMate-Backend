<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; class ProductImage extends Model { protected $guarded=[]; protected $casts=['is_primary'=>'boolean']; }
