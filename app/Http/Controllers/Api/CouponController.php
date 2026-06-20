<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ApplyCouponRequest;
use App\Http\Requests\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    public function index(Request $request)
    {
        $coupons = Coupon::where('business_id', $this->business($request)->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->ok($coupons, 'Coupons');
    }

    public function store(StoreCouponRequest $request)
    {
        $coupon = Coupon::create([
            ...$request->validated(),
            'business_id' => $this->business($request)->id,
            'used_count' => 0,
        ]);

        return $this->ok($coupon, 'Coupon created', 201);
    }

    public function update(StoreCouponRequest $request, int $id)
    {
        $coupon = Coupon::where('business_id', $this->business($request)->id)->findOrFail($id);
        $this->authorize('update', $coupon);
        $coupon->update($request->validated());

        return $this->ok($coupon, 'Coupon updated');
    }

    public function destroy(Request $request, int $id)
    {
        $coupon = Coupon::where('business_id', $this->business($request)->id)->findOrFail($id);
        $this->authorize('delete', $coupon);
        $coupon->delete();

        return $this->ok(null, 'Coupon deleted');
    }

    public function apply(ApplyCouponRequest $request)
    {
        $business = $this->business($request);
        $data = $request->validated();

        $coupon = Coupon::where('business_id', $business->id)
            ->where('code', $data['code'])
            ->first();

        if (!$coupon) {
            return $this->fail('Invalid coupon code', 404);
        }

        $orderTotal = (float) $data['order_total'];

        if (!$coupon->isValid($orderTotal)) {
            return $this->fail('Coupon is expired, inactive, or not applicable to this order', 422);
        }

        $discount = $coupon->type === 'percent'
            ? $orderTotal * ((float) $coupon->value / 100)
            : (float) $coupon->value;

        $discount = min($discount, $orderTotal);
        $finalTotal = max(0, $orderTotal - $discount);

        return $this->ok([
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'discount_amount' => round($discount, 2),
            'final_total' => round($finalTotal, 2),
        ], 'Coupon applied');
    }
}
