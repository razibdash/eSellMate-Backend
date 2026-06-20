<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class AddPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->resolveOrder();

        return $order ? ($this->user()?->can('addPayment', $order) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bkash,nagad,rocket,bank,card,cod,other'],
            'transaction_id' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->resolveOrder();
            if (!$order) {
                return;
            }

            $amount = (float) $this->input('amount', 0);
            if ($amount > $order->dueAmount()) {
                $validator->errors()->add('amount', 'Payment amount cannot exceed the due amount of ' . number_format($order->dueAmount(), 2) . '.');
            }
        });
    }

    public function resolveOrder(): ?Order
    {
        $businessId = $this->attributes->get('current_business')?->id;
        $orderId = $this->route('id');

        if (!$businessId || !$orderId) {
            return null;
        }

        return Order::where('business_id', $businessId)->find($orderId);
    }
}
