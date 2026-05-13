<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PublicStorefrontController extends ApiController
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    public function show(Request $request)
    {
        $storefront = $request->attributes->get('current_storefront');
        $business = $this->business($request);

        $categories = ProductCategory::where('business_id', $business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $featured = Product::where('business_id', $business->id)
            ->where('status', 'active')
            ->where('is_published', true)
            ->where('is_featured', true)
            ->latest()
            ->take(8)
            ->get();

        return $this->ok([
            'storefront' => $storefront,
            'business' => $business->only(['id', 'name', 'slug', 'phone', 'email', 'address']),
            'categories' => $categories,
            'featured_products' => $featured,
        ], 'Storefront details');
    }

    public function products(Request $request)
    {
        $business = $this->business($request);

        $products = Product::where('business_id', $business->id)
            ->with(['category', 'images'])
            ->where('status', 'active')
            ->where('is_published', true)
            ->when($request->q, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%' . $request->q . '%')
                ->orWhere('description', 'like', '%' . $request->q . '%')
                ->orWhere('sku', 'like', '%' . $request->q . '%')))
            ->when($request->category_id, fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('featured'), fn ($query) => $query->where('is_featured', filter_var($request->featured, FILTER_VALIDATE_BOOL)))
            ->latest()
            ->paginate($request->integer('per_page', 24));

        return $this->ok($products, 'Storefront products');
    }

    public function product(Request $request, string $slug)
    {
        $product = Product::where('business_id', $this->business($request)->id)
            ->with(['category', 'images'])
            ->where('status', 'active')
            ->where('is_published', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->ok($product, 'Storefront product details');
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.phone' => ['required', 'string', 'max:50'],
            'customer.delivery_address' => ['required', 'string', 'max:2000'],
            'customer.delivery_note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', 'in:bkash,cod'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $storefront = $request->attributes->get('current_storefront');
        $result = $this->orders->createStorefrontOrder($this->business($request), $storefront, $data);
        /** @var \App\Models\Order $order */
        $order = $result['order'];
        /** @var \App\Models\Payment $payment */
        $payment = $result['payment'];

        if ($data['payment_method'] === 'bkash') {
            $callback = url('/api/payments/bkash/callback');
            $payment = $this->payments->createOrderBkashPayment(
                $payment,
                $callback,
                preg_replace('/\D+/', '', $data['customer']['phone']),
                $order->invoice_number
            );
        }

        return $this->ok([
            'order' => $order,
            'payment' => $payment,
            'redirect_url' => $payment->checkout_url,
            'payment_status_url' => rtrim(config('shopbot.storefront.frontend_base_url'), '/') . '/shop/payment/' . $order->public_reference,
        ], 'Storefront checkout created', 201);
    }

    public function paymentStatus(Request $request, string $reference)
    {
        $order = Order::where('business_id', $this->business($request)->id)
            ->where('public_reference', $reference)
            ->with(['items', 'payments', 'customer'])
            ->firstOrFail();

        return $this->ok($order, 'Storefront payment status');
    }
}
