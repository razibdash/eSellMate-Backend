<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $businessId = $this->attributes->get('current_business')?->id;

        return [
            'order_reference' => [
                'required', 'string',
                Rule::exists('orders', 'public_reference')->where('business_id', $businessId),
            ],
            'product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
