<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $appends = [
        'average_rating',
        'review_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: fn () => round((float) $this->reviews()->approved()->avg('rating'), 1),
        );
    }

    protected function reviewCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviews()->approved()->count(),
        );
    }
}
