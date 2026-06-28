<?php

namespace App\Http\Requests;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class ApproveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');
        $reviewModel = $review instanceof Review ? $review : Review::find($review);

        return $reviewModel ? ($this->user()?->can('approve', $reviewModel) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'is_approved' => ['required', 'boolean'],
        ];
    }
}
