<?php

namespace App\Http\Requests;

use App\Models\FlashSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FlashSale::class) ?? false;
    }

    public function rules(): array
    {
        $businessId = $this->attributes->get('current_business')?->id;

        return [
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'discount_percent' => ['required', 'numeric', 'min:1', 'max:90'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
