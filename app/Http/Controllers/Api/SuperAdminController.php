<?php

namespace App\Http\Controllers\Api;

use App\Models\AiGeneration;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Storefront;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminController extends ApiController
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->is_super_admin, 403, 'Super admin only');
    }

    public function dashboard(Request $request)
    {
        $this->guard($request);
        return $this->ok([
            'businesses' => Business::count(),
            'active_businesses' => Business::where('status','active')->count(),
            'users' => User::count(),
            'orders' => Order::count(),
            'revenue' => (float) Order::whereNotIn('order_status',['cancelled','returned'])->sum('total_amount'),
            'active_subscriptions' => Subscription::where('status','active')->count(),
            'ai_usage' => AiGeneration::where('created_at','>=',now()->startOfMonth())->count(),
            'ai_generations_this_month' => AiGeneration::where('created_at','>=',now()->startOfMonth())->count(),
        ], 'Super admin dashboard');
    }

    public function businesses(Request $request)
    {
        $this->guard($request);
        $rows = Business::with(['owner','subscription.plan'])->when($request->status, fn($q)=>$q->where('status',$request->status))->latest()->paginate($request->integer('per_page',20));
        return $this->ok($rows, 'Business list');
    }

    public function users(Request $request)
    {
        $this->guard($request);
        return $this->ok(User::latest()->paginate($request->integer('per_page',20)), 'User list');
    }

    public function plans(Request $request)
    {
        $this->guard($request);
        return $this->ok(Plan::orderBy('price_monthly')->paginate($request->integer('per_page',20)), 'Plan list');
    }

    public function subscriptions(Request $request)
    {
        $this->guard($request);
        return $this->ok(Subscription::with(['business','plan'])->latest()->paginate($request->integer('per_page',20)), 'Subscription list');
    }

    public function subscriptionPayments(Request $request)
    {
        $this->guard($request);

        return $this->ok(
            SubscriptionPayment::with(['business','plan','subscription'])
                ->when($request->status, fn ($query) => $query->where('status', $request->status))
                ->when($request->payment_method, fn ($query) => $query->where('payment_method', $request->payment_method))
                ->latest()
                ->paginate($request->integer('per_page', 20)),
            'Subscription payment list'
        );
    }

    public function approveSubscriptionPayment(Request $request, int $id)
    {
        $this->guard($request);
        $payment = SubscriptionPayment::where('payment_method', 'bank')->findOrFail($id);

        if ($payment->status !== 'pending') {
            return $this->fail('Only pending bank payments can be approved.', 422);
        }

        $payment = $this->subscriptions->activateFromPayment($payment, $payment->transaction_id);
        $payment->update(['approved_by' => $request->user()->id, 'approved_at' => now()]);

        return $this->ok($payment->fresh(['business','plan','subscription']), 'Bank payment approved');
    }

    public function rejectSubscriptionPayment(Request $request, int $id)
    {
        $this->guard($request);
        $data = $request->validate(['note' => ['nullable','string','max:1000']]);
        $payment = SubscriptionPayment::where('payment_method', 'bank')->findOrFail($id);

        if ($payment->status !== 'pending') {
            return $this->fail('Only pending bank payments can be rejected.', 422);
        }

        $payment->update([
            'status' => 'failed',
            'note' => $data['note'] ?? $payment->note,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
        ]);

        return $this->ok($payment->fresh(['business','plan','subscription']), 'Bank payment rejected');
    }

    public function logs(Request $request)
    {
        $this->guard($request);
        return $this->ok(AuditLog::latest()->paginate($request->integer('per_page',20)), 'Audit logs');
    }

    public function storefronts(Request $request)
    {
        $this->guard($request);
        return $this->ok(Storefront::with(['business.owner'])->latest()->paginate($request->integer('per_page', 20)), 'Storefront list');
    }

    public function products(Request $request)
    {
        $this->guard($request);
        return $this->ok(
            Product::with(['business', 'category'])
                ->when($request->business_id, fn ($query) => $query->where('business_id', $request->integer('business_id')))
                ->latest()
                ->paginate($request->integer('per_page', 20)),
            'Global product list'
        );
    }

    public function orders(Request $request)
    {
        $this->guard($request);
        return $this->ok(
            Order::with(['business', 'customer', 'payments'])
                ->when($request->business_id, fn ($query) => $query->where('business_id', $request->integer('business_id')))
                ->latest()
                ->paginate($request->integer('per_page', 20)),
            'Global order list'
        );
    }

    public function payments(Request $request)
    {
        $this->guard($request);
        return $this->ok(
            Payment::with(['business', 'order'])
                ->when($request->business_id, fn ($query) => $query->where('business_id', $request->integer('business_id')))
                ->latest()
                ->paginate($request->integer('per_page', 20)),
            'Global payment list'
        );
    }

    public function reports(Request $request)
    {
        $this->guard($request);
        return $this->ok([
            'total_sales' => (float) Order::whereNotIn('order_status', ['cancelled', 'returned'])->sum('total_amount'),
            'paid_sales' => (float) Payment::where('payment_status', 'paid')->sum('amount'),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'website_orders' => Order::where('order_source', 'website')->count(),
            'storefronts' => Storefront::count(),
            'active_storefronts' => Storefront::where('status', 'active')->count(),
        ], 'Super admin reports');
    }

    public function updateBusinessStatus(Request $request, int $id)
    {
        $this->guard($request);
        $data = $request->validate(['status'=>['required','in:active,suspended,inactive']]);
        $business = Business::findOrFail($id);
        $business->update($data);
        return $this->ok($business, 'Business status updated');
    }

    public function updateStorefrontStatus(Request $request, int $id)
    {
        $this->guard($request);
        $data = $request->validate(['status' => ['required', 'in:active,suspended,inactive']]);
        $storefront = Storefront::findOrFail($id);
        $storefront->update($data);
        return $this->ok($storefront, 'Storefront status updated');
    }

    public function deleteResource(Request $request, string $resource, int $id)
    {
        $this->guard($request);

        $model = match ($resource) {
            'products' => Product::class,
            'categories' => ProductCategory::class,
            'customers' => Customer::class,
            'orders' => Order::class,
            'invoices' => Invoice::class,
            'payments' => Payment::class,
            'stock-movements' => StockMovement::class,
            'storefronts' => Storefront::class,
            default => null,
        };

        abort_unless($model, 404, 'Unknown resource type');

        $entity = $model::findOrFail($id);
        $entity->delete();

        return $this->ok(null, ucfirst($resource) . ' deleted');
    }
}
