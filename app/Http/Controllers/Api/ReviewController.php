<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ApproveReviewRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends ApiController
{
    public function productReviews(Request $request, int $product)
    {
        $business = $this->business($request);

        Product::where('business_id', $business->id)->findOrFail($product);

        $reviews = Review::where('business_id', $business->id)
            ->where('product_id', $product)
            ->approved()
            ->with('customer:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return $this->ok($reviews, 'Product reviews');
    }

    public function store(StoreReviewRequest $request)
    {
        $business = $this->business($request);
        $data = $request->validated();

        $order = Order::where('business_id', $business->id)
            ->where('public_reference', $data['order_reference'])
            ->firstOrFail();

        if ($order->order_status !== 'delivered') {
            return $this->fail('Reviews can only be submitted after the order has been delivered.', 422);
        }

        $hasProduct = $order->items()->where('product_id', $data['product_id'])->exists();

        if (!$hasProduct) {
            return $this->fail('This product was not part of the selected order.', 422);
        }

        $existing = Review::where('order_id', $order->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            return $this->fail('You have already reviewed this product for this order.', 422);
        }

        $review = Review::create([
            'business_id' => $business->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'product_id' => $data['product_id'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'is_approved' => false,
        ]);

        return $this->ok($review, 'Review submitted and pending approval', 201);
    }

    public function adminIndex(Request $request)
    {
        $business = $this->business($request);

        $reviews = Review::where('business_id', $business->id)
            ->with(['customer:id,name', 'product:id,name'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->ok($reviews, 'Reviews');
    }

    public function approve(ApproveReviewRequest $request, int $review)
    {
        $business = $this->business($request);
        $reviewModel = Review::where('business_id', $business->id)->findOrFail($review);

        $reviewModel->update(['is_approved' => $request->validated()['is_approved']]);

        return $this->ok($reviewModel->fresh(['customer:id,name', 'product:id,name']), $reviewModel->is_approved ? 'Review approved' : 'Review rejected');
    }
}
