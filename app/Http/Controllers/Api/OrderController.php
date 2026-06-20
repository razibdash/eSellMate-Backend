<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\AddPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $business = $this->business($request);
        $orders = Order::where('business_id', $business->id)
            ->with(['customer', 'items', 'payments', 'invoice'])
            ->when($request->q, fn($q) => $q->where(fn($w) => $w->where('order_number', 'like', '%' . $request->q . '%')->orWhere('invoice_number', 'like', '%' . $request->q . '%')->orWhere('customer_name_snapshot', 'like', '%' . $request->q . '%')->orWhere('customer_phone_snapshot', 'like', '%' . $request->q . '%')))
            ->when($request->order_status, fn($q) => $q->where('order_status', $request->order_status))
            ->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->delivery_status, fn($q) => $q->where('delivery_status', $request->delivery_status))
            ->when($request->order_source, fn($q) => $q->where('order_source', $request->order_source))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->ok($orders, 'Order list');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_without:customer_id', 'string'],
            'customer.phone' => ['required_without:customer_id', 'string'],
            'customer.email' => ['nullable', 'email'],
            'customer.address' => ['nullable', 'string'],
            'customer.area' => ['nullable', 'string'],
            'customer.city' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
            'order_source' => ['nullable', 'in:facebook,whatsapp,instagram,phone,website,walkin,other'],
            'order_status' => ['nullable', 'in:pending,confirmed,processing,packed,shipped,delivered,cancelled,returned'],
            'payment_status' => ['nullable', 'in:unpaid,partial,paid,refunded'],
            'delivery_status' => ['nullable', 'in:not_assigned,ready,sent,in_transit,delivered,failed,returned'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,bkash,nagad,rocket,bank,card,cod,other'],
            'transaction_id' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $business = $this->business($request);

        // Check if business is on free plan and customer has already purchased
        $subscription = $business->subscription;
        if ($subscription && $subscription->plan && $subscription->plan->price_monthly == 0) {
            $customerId = $customer?->id ?? null;
            if ($customerId) {
                $existingOrderCount = Order::where('business_id', $business->id)
                    ->where('customer_id', $customerId)
                    ->count();
                if ($existingOrderCount > 0) {
                    return $this->fail('Free plan allows only one purchase per customer.', 403);
                }
            }
        }

        return DB::transaction(function () use ($data, $business, $request) {
            $customer = $this->resolveCustomer($business->id, $data);
            $items = $this->prepareItems($business->id, $data['items']);
            $subtotal = collect($items)->sum('line_total');
            $discount = (float)($data['discount_amount'] ?? 0);
            $delivery = (float)($data['delivery_charge'] ?? 0);
            $paid = (float)($data['paid_amount'] ?? 0);
            $total = max(0, $subtotal - $discount + $delivery);

            $orderStatus = $data['order_status'] ?? 'pending';
            $paymentStatus = $data['payment_status'] ?? $this->orders->paymentStatus($paid, $total);
            $order = Order::create([
                'business_id' => $business->id,
                'customer_id' => $customer?->id,
                'created_by' => $request->user()->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'order_number' => $this->orders->nextOrderNumber($business->id),
                'invoice_number' => $this->orders->nextInvoiceNumber($business),
                'order_source' => $data['order_source'] ?? 'facebook',
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'delivery_status' => $data['delivery_status'] ?? 'not_assigned',
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'delivery_charge' => $delivery,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_amount' => max(0, $total - $paid),
                'customer_name_snapshot' => $customer?->name ?? data_get($data, 'customer.name'),
                'customer_phone_snapshot' => $customer?->phone ?? data_get($data, 'customer.phone'),
                'delivery_address_snapshot' => $data['delivery_address'] ?? $customer?->address ?? data_get($data, 'customer.address'),
                'note' => $data['note'] ?? null,
                'confirmed_at' => in_array($orderStatus, ['confirmed', 'processing', 'packed', 'shipped', 'delivered']) ? now() : null,
                'delivered_at' => $orderStatus === 'delivered' ? now() : null,
                'cancelled_at' => $orderStatus === 'cancelled' ? now() : null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            OrderStatusHistory::create(['order_id' => $order->id, 'changed_by' => $request->user()->id, 'previous_status' => null, 'new_status' => $orderStatus, 'status_type' => 'order', 'note' => 'Order created', 'created_at' => now()]);

            if ($paid > 0) {
                Payment::create(['business_id' => $business->id, 'order_id' => $order->id, 'payment_method' => $data['payment_method'] ?? 'cod', 'amount' => $paid, 'transaction_id' => $data['transaction_id'] ?? null, 'payment_status' => 'paid', 'paid_at' => now(), 'created_by' => $request->user()->id]);
            }

            Invoice::create(['business_id' => $business->id, 'order_id' => $order->id, 'invoice_number' => $order->invoice_number, 'invoice_data_json' => $this->orders->invoiceSnapshot($order->load(['business', 'customer', 'items'])), 'generated_at' => now()]);

            if (in_array($orderStatus, ['confirmed', 'processing', 'packed', 'shipped', 'delivered'])) {
                $this->orders->deductStock($order, $request->user()->id);
            }
            if ($customer) $this->orders->updateCustomerStats($customer);

            return $this->ok($order->fresh(['customer', 'items', 'payments', 'invoice']), 'Order created', 201);
        });
    }

    public function show(Request $request, int $id)
    {
        return $this->ok(Order::where('business_id', $this->business($request)->id)->with(['business', 'customer', 'items.product', 'payments', 'histories', 'invoice'])->findOrFail($id), 'Order details');
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'order_source' => ['nullable', 'in:facebook,whatsapp,instagram,phone,website,walkin,other'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string'],
            'note' => ['nullable', 'string']
        ]);
        $order = Order::where('business_id', $this->business($request)->id)->findOrFail($id);
        $subtotal = (float)$order->subtotal;
        $discount = array_key_exists('discount_amount', $data) ? (float)$data['discount_amount'] : (float)$order->discount_amount;
        $delivery = array_key_exists('delivery_charge', $data) ? (float)$data['delivery_charge'] : (float)$order->delivery_charge;
        $total = max(0, $subtotal - $discount + $delivery);
        $data['total_amount'] = $total;
        $data['due_amount'] = max(0, $total - (float)$order->paid_amount);
        $data['payment_status'] = $this->orders->paymentStatus((float)$order->paid_amount, $total);
        if (isset($data['delivery_address'])) $data['delivery_address_snapshot'] = $data['delivery_address'];
        unset($data['delivery_address']);
        $order->update($data);
        return $this->ok($order->fresh(['items', 'payments', 'invoice']), 'Order updated');
    }

    public function destroy(Request $request, int $id)
    {
        return $this->updateStatus($request, $id, 'cancelled');
    }

    public function updateStatus(Request $request, int $id, ?string $status = null)
    {
        $data = $request->validate(['order_status' => ['nullable', 'in:pending,confirmed,processing,packed,shipped,delivered,cancelled,returned'], 'note' => ['nullable', 'string']]);
        $status = $status ?? $data['order_status'] ?? null;
        if (!$status) return $this->fail('order_status required', 422);
        $order = Order::where('business_id', $this->business($request)->id)->with('items')->findOrFail($id);
        $old = $order->order_status;

        DB::transaction(function () use ($order, $old, $status, $request, $data) {
            $order->update([
                'order_status' => $status,
                'confirmed_at' => in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'delivered']) && !$order->confirmed_at ? now() : $order->confirmed_at,
                'delivered_at' => $status === 'delivered' ? now() : $order->delivered_at,
                'cancelled_at' => $status === 'cancelled' ? now() : $order->cancelled_at,
            ]);
            OrderStatusHistory::create(['order_id' => $order->id, 'changed_by' => $request->user()->id, 'previous_status' => $old, 'new_status' => $status, 'status_type' => 'order', 'note' => $data['note'] ?? null, 'created_at' => now()]);
            if (!$order->stock_deducted && in_array($status, ['confirmed', 'processing', 'packed', 'shipped', 'delivered'])) $this->orders->deductStock($order, $request->user()->id);
            if ($order->stock_deducted && in_array($status, ['cancelled', 'returned'])) $this->orders->returnStock($order, $request->user()->id, $status === 'returned' ? 'return' : 'order_cancel');
            if ($order->customer) $this->orders->updateCustomerStats($order->customer);
        });

        return $this->ok($order->fresh(['items', 'histories']), 'Order status updated');
    }

    public function updatePaymentStatus(Request $request, int $id)
    {
        $data = $request->validate(['payment_status' => ['required', 'in:unpaid,partial,paid,refunded'], 'note' => ['nullable', 'string']]);
        $order = Order::where('business_id', $this->business($request)->id)->findOrFail($id);
        $old = $order->payment_status;
        $order->update(['payment_status' => $data['payment_status']]);
        OrderStatusHistory::create(['order_id' => $order->id, 'changed_by' => $request->user()->id, 'previous_status' => $old, 'new_status' => $data['payment_status'], 'status_type' => 'payment', 'note' => $data['note'] ?? null, 'created_at' => now()]);
        return $this->ok($order, 'Payment status updated');
    }

    public function updateDeliveryStatus(Request $request, int $id)
    {
        $data = $request->validate(['delivery_status' => ['required', 'in:not_assigned,ready,sent,in_transit,delivered,failed,returned'], 'note' => ['nullable', 'string']]);
        $order = Order::where('business_id', $this->business($request)->id)->findOrFail($id);
        $old = $order->delivery_status;
        $order->update(['delivery_status' => $data['delivery_status'], 'delivered_at' => $data['delivery_status'] === 'delivered' ? now() : $order->delivered_at]);
        OrderStatusHistory::create(['order_id' => $order->id, 'changed_by' => $request->user()->id, 'previous_status' => $old, 'new_status' => $data['delivery_status'], 'status_type' => 'delivery', 'note' => $data['note'] ?? null, 'created_at' => now()]);
        return $this->ok($order, 'Delivery status updated');
    }

    public function addPayment(AddPaymentRequest $request, int $id)
    {
        $data = $request->validated();
        $order = $request->resolveOrder();
        $payment = DB::transaction(function () use ($order, $data, $request) {
            $payment = Payment::create(['business_id' => $order->business_id, 'order_id' => $order->id, 'payment_method' => $data['payment_method'], 'amount' => $data['amount'], 'transaction_id' => $data['transaction_id'] ?? null, 'payment_status' => 'paid', 'paid_at' => $data['paid_at'] ?? now(), 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
            $paid = (float)$order->payments()->where('payment_status', 'paid')->sum('amount');
            $order->update(['paid_amount' => $paid, 'due_amount' => max(0, (float)$order->total_amount - $paid), 'payment_status' => $this->orders->paymentStatus($paid, (float)$order->total_amount)]);
            OrderStatusHistory::create(['order_id' => $order->id, 'changed_by' => $request->user()->id, 'previous_status' => null, 'new_status' => $order->payment_status, 'status_type' => 'payment', 'note' => 'Payment added', 'created_at' => now()]);
            if ($order->customer) $this->orders->updateCustomerStats($order->customer);
            return $payment;
        });
        $order->refresh();
        return $this->ok([
            'payment' => $payment,
            'total_amount' => (float) $order->total_amount,
            'paid_amount' => (float) $order->paid_amount,
            'due_amount' => $order->dueAmount(),
            'is_paid_full' => $order->isPaidFull(),
        ], 'Payment added', 201);
    }

    public function invoice(Request $request, int $id)
    {
        $order = Order::where('business_id', $this->business($request)->id)->with(['business', 'customer', 'items', 'payments', 'invoice'])->findOrFail($id);
        $invoice = $order->invoice ?: Invoice::create(['business_id' => $order->business_id, 'order_id' => $order->id, 'invoice_number' => $order->invoice_number ?: $this->orders->nextInvoiceNumber($order->business), 'invoice_data_json' => $this->orders->invoiceSnapshot($order), 'generated_at' => now()]);
        return $this->ok($invoice->load('order'), 'Invoice');
    }

    public function generateInvoice(Request $request, int $id)
    {
        $order = Order::where('business_id', $this->business($request)->id)->with(['business', 'customer', 'items', 'payments', 'invoice'])->findOrFail($id);
        $invoice = $order->invoice ?: Invoice::create(['business_id' => $order->business_id, 'order_id' => $order->id, 'invoice_number' => $order->invoice_number ?: $this->orders->nextInvoiceNumber($order->business), 'generated_at' => now()]);
        $invoice->update(['invoice_data_json' => $this->orders->invoiceSnapshot($order), 'pdf_path' => $this->orders->generateInvoiceHtml($order, $invoice), 'generated_at' => now()]);
        return $this->ok($invoice->refresh(), 'Invoice generated');
    }

    public function whatsappMessage(Request $request, int $id)
    {
        $order = Order::where('business_id', $this->business($request)->id)->with('business')->findOrFail($id);
        $message = "Hello {$order->customer_name_snapshot}, your order {$order->invoice_number} has been confirmed. Total amount: {$order->total_amount}. Thank you for shopping with {$order->business->name}.";
        $phone = preg_replace('/\D+/', '', (string)$order->customer_phone_snapshot);
        return $this->ok(['message' => $message, 'url' => 'https://wa.me/' . $phone . '?text=' . rawurlencode($message)], 'WhatsApp message');
    }

    private function resolveCustomer(int $businessId, array $data): ?Customer
    {
        if (!empty($data['customer_id'])) return Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        if (empty($data['customer'])) return null;
        $payload = $data['customer'];
        $customer = Customer::updateOrCreate(['business_id' => $businessId, 'phone' => $payload['phone']], ['name' => $payload['name'], 'email' => $payload['email'] ?? null, 'address' => $payload['address'] ?? null, 'area' => $payload['area'] ?? null, 'city' => $payload['city'] ?? null, 'status' => 'active']);
        if (!empty($payload['address'])) {
            CustomerAddress::firstOrCreate(['customer_id' => $customer->id, 'address' => $payload['address']], ['name' => $payload['name'], 'phone' => $payload['phone'], 'area' => $payload['area'] ?? null, 'city' => $payload['city'] ?? null, 'is_default' => true]);
        }
        return $customer;
    }

    private function prepareItems(int $businessId, array $items): array
    {
        return collect($items)->map(function ($item) use ($businessId) {
            $product = Product::where('business_id', $businessId)->findOrFail($item['product_id']);
            $unit = (float)($item['unit_price'] ?? ($product->discount_price ?: $product->price));
            $qty = (int)$item['quantity'];
            $discount = (float)($item['discount_amount'] ?? 0);
            return ['product_id' => $product->id, 'product_name_snapshot' => $product->name, 'sku_snapshot' => $product->sku, 'unit_price' => $unit, 'quantity' => $qty, 'discount_amount' => $discount, 'line_total' => max(0, $unit * $qty - $discount)];
        })->all();
    }
}
