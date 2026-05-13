<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storefront;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderService
{
    public function createStorefrontOrder(Business $business, Storefront $storefront, array $payload): array
    {
        return DB::transaction(function () use ($business, $storefront, $payload) {
            $customer = $this->resolveCustomer($business->id, $payload['customer']);
            $items = $this->prepareItems($business->id, $payload['items']);
            $subtotal = collect($items)->sum('line_total');
            $delivery = (float) ($payload['delivery_charge'] ?? $storefront->delivery_charge ?? 0);
            $total = max(0, $subtotal + $delivery);
            $paymentMethod = $payload['payment_method'];
            $isCod = $paymentMethod === 'cod';

            $order = Order::create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'order_number' => $this->nextOrderNumber($business->id),
                'invoice_number' => $this->nextInvoiceNumber($business),
                'public_reference' => 'web-' . strtoupper(str()->random(12)),
                'order_source' => 'website',
                'order_status' => $isCod ? 'confirmed' : 'pending',
                'payment_status' => $isCod ? 'unpaid' : 'unpaid',
                'delivery_status' => 'not_assigned',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'delivery_charge' => $delivery,
                'total_amount' => $total,
                'paid_amount' => 0,
                'due_amount' => $total,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'delivery_address_snapshot' => $payload['customer']['delivery_address'],
                'payment_method_snapshot' => $paymentMethod,
                'customer_note' => $payload['customer']['delivery_note'] ?? null,
                'note' => $payload['customer']['delivery_note'] ?? null,
                'storefront_meta' => [
                    'storefront_id' => $storefront->id,
                    'subdomain' => $storefront->subdomain,
                    'checkout_phone' => $payload['customer']['phone'],
                ],
                'confirmed_at' => $isCod ? now() : null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'changed_by' => null,
                'previous_status' => null,
                'new_status' => $order->order_status,
                'status_type' => 'order',
                'note' => 'Storefront order created',
                'created_at' => now(),
            ]);

            $payment = Payment::create([
                'business_id' => $business->id,
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $total,
                'payment_status' => $isCod ? 'pending' : 'pending',
                'order_reference' => $order->public_reference,
                'note' => 'Created from public storefront checkout',
            ]);

            Invoice::create([
                'business_id' => $business->id,
                'order_id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'invoice_data_json' => $this->invoiceSnapshot($order->load(['business', 'customer', 'items'])),
                'generated_at' => now(),
            ]);

            if ($isCod) {
                $this->confirmOrderForCod($order);
            }

            $this->updateCustomerStats($customer);

            return [
                'order' => $order->fresh(['items', 'customer', 'payments', 'business']),
                'payment' => $payment->fresh(),
            ];
        });
    }

    public function markPaymentSuccessful(Payment $payment, ?string $transactionId = null, array $gatewayResponse = []): Payment
    {
        return DB::transaction(function () use ($payment, $transactionId, $gatewayResponse) {
            $payment->update([
                'payment_status' => 'paid',
                'transaction_id' => $transactionId ?: $payment->transaction_id,
                'paid_at' => now(),
                'gateway_response' => $gatewayResponse ?: $payment->gateway_response,
            ]);

            $order = $payment->order()->with(['items', 'customer', 'business', 'invoice'])->firstOrFail();
            $this->confirmPaidOrder($order);

            return $payment->fresh(['order']);
        });
    }

    public function markPaymentFailed(Payment $payment, string $status, array $gatewayResponse = []): Payment
    {
        $payment->update([
            'payment_status' => 'failed',
            'gateway_response' => array_merge($payment->gateway_response ?? [], $gatewayResponse, ['callback_status' => $status]),
        ]);

        return $payment->fresh(['order']);
    }

    public function nextOrderNumber(int $businessId): string
    {
        $seq = Order::where('business_id', $businessId)->whereYear('created_at', now()->year)->count() + 1;

        return 'ORD-' . now()->format('Y') . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    public function nextInvoiceNumber(Business $business): string
    {
        $seq = Order::where('business_id', $business->id)->whereYear('created_at', now()->year)->count() + 1;

        return strtoupper($business->invoice_prefix ?: 'SB') . '-' . now()->format('Y') . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    public function paymentStatus(float $paid, float $total): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid < $total) {
            return 'partial';
        }

        return 'paid';
    }

    public function deductStock(Order $order, ?int $userId = null): void
    {
        foreach ($order->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $product = Product::lockForUpdate()->find($item->product_id);
            if (!$product) {
                continue;
            }

            $prev = $product->stock_quantity;
            $new = max(0, $prev - $item->quantity);
            $product->update(['stock_quantity' => $new]);

            StockMovement::create([
                'business_id' => $order->business_id,
                'product_id' => $product->id,
                'movement_type' => 'order_sale',
                'quantity' => -1 * $item->quantity,
                'previous_stock' => $prev,
                'new_stock' => $new,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'note' => 'Stock deducted for order ' . $order->order_number,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }

        $order->update(['stock_deducted' => true]);
    }

    public function returnStock(Order $order, ?int $userId = null, string $type = 'order_cancel'): void
    {
        foreach ($order->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $product = Product::lockForUpdate()->find($item->product_id);
            if (!$product) {
                continue;
            }

            $prev = $product->stock_quantity;
            $new = $prev + $item->quantity;
            $product->update(['stock_quantity' => $new]);

            StockMovement::create([
                'business_id' => $order->business_id,
                'product_id' => $product->id,
                'movement_type' => $type,
                'quantity' => $item->quantity,
                'previous_stock' => $prev,
                'new_stock' => $new,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'note' => 'Stock returned for order ' . $order->order_number,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }

        $order->update(['stock_deducted' => false]);
    }

    public function updateCustomerStats(Customer $customer): void
    {
        $orders = $customer->orders()->whereNotIn('order_status', ['cancelled', 'returned']);

        $customer->update([
            'total_orders' => (clone $orders)->count(),
            'total_spent' => (clone $orders)->sum('paid_amount'),
            'last_order_at' => (clone $orders)->latest()->value('created_at'),
        ]);
    }

    public function invoiceSnapshot(Order $order): array
    {
        return [
            'business' => $order->business?->only(['name', 'phone', 'email', 'address', 'currency', 'invoice_footer']),
            'customer' => [
                'name' => $order->customer_name_snapshot,
                'phone' => $order->customer_phone_snapshot,
                'address' => $order->delivery_address_snapshot,
            ],
            'order' => $order->only([
                'order_number',
                'invoice_number',
                'public_reference',
                'order_source',
                'order_status',
                'payment_status',
                'delivery_status',
                'subtotal',
                'discount_amount',
                'delivery_charge',
                'total_amount',
                'paid_amount',
                'due_amount',
                'created_at',
            ]),
            'items' => $order->items
                ->map(fn (OrderItem $item) => $item->only(['product_name_snapshot', 'sku_snapshot', 'unit_price', 'quantity', 'discount_amount', 'line_total']))
                ->values()
                ->all(),
        ];
    }

    public function generateInvoiceHtml(Order $order, Invoice $invoice): string
    {
        $path = "businesses/{$order->business_id}/invoices/{$invoice->invoice_number}.html";
        $html = view('invoices.basic', ['order' => $order, 'invoice' => $invoice])->render();
        Storage::disk('public')->put($path, $html);

        return $path;
    }

    private function confirmOrderForCod(Order $order): void
    {
        $order->update([
            'order_status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'changed_by' => null,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
            'status_type' => 'order',
            'note' => 'Cash on delivery order auto-confirmed',
            'created_at' => now(),
        ]);

        $this->deductStock($order->load('items'));
    }

    private function confirmPaidOrder(Order $order): void
    {
        $paid = (float) $order->payments()->where('payment_status', 'paid')->sum('amount');
        $paymentStatus = $this->paymentStatus($paid, (float) $order->total_amount);
        $previousOrderStatus = $order->order_status;

        $order->update([
            'order_status' => 'confirmed',
            'payment_status' => $paymentStatus,
            'paid_amount' => $paid,
            'due_amount' => max(0, (float) $order->total_amount - $paid),
            'confirmed_at' => $order->confirmed_at ?: now(),
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'changed_by' => null,
            'previous_status' => $previousOrderStatus,
            'new_status' => 'confirmed',
            'status_type' => 'order',
            'note' => 'Storefront payment confirmed',
            'created_at' => now(),
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'changed_by' => null,
            'previous_status' => 'unpaid',
            'new_status' => $paymentStatus,
            'status_type' => 'payment',
            'note' => 'Payment received from storefront gateway',
            'created_at' => now(),
        ]);

        if (!$order->stock_deducted) {
            $this->deductStock($order->load('items'));
        }

        if ($order->customer) {
            $this->updateCustomerStats($order->customer);
        }
    }

    private function resolveCustomer(int $businessId, array $payload): Customer
    {
        $customer = Customer::updateOrCreate(
            ['business_id' => $businessId, 'phone' => $payload['phone']],
            [
                'name' => $payload['name'],
                'address' => $payload['delivery_address'],
                'note' => $payload['delivery_note'] ?? null,
                'status' => 'active',
            ]
        );

        CustomerAddress::updateOrCreate(
            ['customer_id' => $customer->id, 'address' => $payload['delivery_address']],
            [
                'name' => $payload['name'],
                'phone' => $payload['phone'],
                'address' => $payload['delivery_address'],
                'is_default' => true,
            ]
        );

        return $customer;
    }

    private function prepareItems(int $businessId, array $items): array
    {
        return collect($items)->map(function (array $item) use ($businessId) {
            $product = Product::where('business_id', $businessId)
                ->where('status', 'active')
                ->where('is_published', true)
                ->findOrFail($item['product_id']);

            $qty = (int) $item['quantity'];
            abort_if($product->stock_quantity < $qty, 422, 'Insufficient stock for ' . $product->name);

            $unit = (float) ($product->discount_price ?: $product->price);

            return [
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'sku_snapshot' => $product->sku,
                'unit_price' => $unit,
                'quantity' => $qty,
                'discount_amount' => 0,
                'line_total' => max(0, $unit * $qty),
            ];
        })->all();
    }
}
