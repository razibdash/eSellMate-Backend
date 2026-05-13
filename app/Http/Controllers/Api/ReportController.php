<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends ApiController
{
    public function dashboard(Request $request)
    {
        $bid = $this->business($request)->id;
        $today = now()->toDateString();
        $data = [
            'today_orders' => Order::where('business_id',$bid)->whereDate('created_at',$today)->count(),
            'today_sales' => (float) Order::where('business_id',$bid)->whereDate('created_at',$today)->whereNotIn('order_status',['cancelled','returned'])->sum('total_amount'),
            'pending_orders' => Order::where('business_id',$bid)->where('order_status','pending')->count(),
            'delivered_orders' => Order::where('business_id',$bid)->where('order_status','delivered')->count(),
            'unpaid_amount' => (float) Order::where('business_id',$bid)->sum('due_amount'),
            'low_stock_products' => Product::where('business_id',$bid)->whereColumn('stock_quantity','<=','low_stock_alert')->count(),
            'recent_orders' => Order::where('business_id',$bid)->with('customer')->latest()->limit(10)->get(),
            'top_products' => $this->topProducts($bid, 7),
            'daily_sales' => $this->salesSeries($bid, now()->subDays(6)->toDateString(), $today),
            'order_status_chart' => Order::where('business_id',$bid)->select('order_status', DB::raw('count(*) as total'))->groupBy('order_status')->pluck('total','order_status'),
            'payment_status_chart' => Order::where('business_id',$bid)->select('payment_status', DB::raw('count(*) as total'))->groupBy('payment_status')->pluck('total','payment_status'),
        ];
        return $this->ok($data, 'Dashboard summary');
    }

    public function sales(Request $request)
    {
        $bid = $this->business($request)->id;
        $from = $request->date_from ?: now()->startOfMonth()->toDateString();
        $to = $request->date_to ?: now()->toDateString();
        return $this->ok(['summary'=>$this->salesSummary($bid,$from,$to),'series'=>$this->salesSeries($bid,$from,$to)], 'Sales report');
    }

    public function products(Request $request)
    {
        $bid = $this->business($request)->id;
        $from = $request->date_from ?: now()->subDays(30)->toDateString();
        $to = $request->date_to ?: now()->toDateString();
        $rows = OrderItem::query()
            ->join('orders','orders.id','=','order_items.order_id')
            ->where('orders.business_id',$bid)
            ->whereNotIn('orders.order_status',['cancelled','returned'])
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$from,$to])
            ->select('order_items.product_id','order_items.product_name_snapshot', DB::raw('sum(order_items.quantity) as quantity_sold'), DB::raw('sum(order_items.line_total) as sales_amount'))
            ->groupBy('order_items.product_id','order_items.product_name_snapshot')
            ->orderByDesc('quantity_sold')->paginate($request->integer('per_page',20));
        return $this->ok($rows, 'Product sales report');
    }

    public function customers(Request $request)
    {
        $bid = $this->business($request)->id;
        $rows = Customer::where('business_id',$bid)->orderByDesc('total_spent')->paginate($request->integer('per_page',20));
        return $this->ok($rows, 'Customer report');
    }

    public function payments(Request $request)
    {
        $bid = $this->business($request)->id;
        $from = $request->date_from ?: now()->startOfMonth()->toDateString();
        $to = $request->date_to ?: now()->toDateString();
        $rows = Payment::where('business_id',$bid)
            ->whereBetween(DB::raw('DATE(created_at)'), [$from,$to])
            ->select('payment_method','payment_status', DB::raw('count(*) as total_count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('payment_method','payment_status')->get();
        return $this->ok($rows, 'Payment report');
    }

    public function delivery(Request $request)
    {
        $bid = $this->business($request)->id;
        $rows = Order::where('business_id',$bid)->select('delivery_status', DB::raw('count(*) as total'), DB::raw('sum(total_amount) as amount'))->groupBy('delivery_status')->get();
        return $this->ok($rows, 'Delivery report');
    }

    public function lowStock(Request $request)
    {
        $bid = $this->business($request)->id;
        return $this->ok(Product::where('business_id',$bid)->whereColumn('stock_quantity','<=','low_stock_alert')->orderBy('stock_quantity')->get(), 'Low stock report');
    }

    private function salesSummary(int $bid, string $from, string $to): array
    {
        $orders = Order::where('business_id',$bid)->whereNotIn('order_status',['cancelled','returned'])->whereBetween(DB::raw('DATE(created_at)'), [$from,$to]);
        return ['orders'=>(clone $orders)->count(),'sales'=>(float)(clone $orders)->sum('total_amount'),'paid'=>(float)(clone $orders)->sum('paid_amount'),'due'=>(float)(clone $orders)->sum('due_amount')];
    }

    private function salesSeries(int $bid, string $from, string $to)
    {
        return Order::where('business_id',$bid)
            ->whereNotIn('order_status',['cancelled','returned'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$from,$to])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as orders'), DB::raw('sum(total_amount) as sales'))
            ->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->get();
    }

    private function topProducts(int $bid, int $days)
    {
        return OrderItem::query()->join('orders','orders.id','=','order_items.order_id')
            ->where('orders.business_id',$bid)->where('orders.created_at','>=',now()->subDays($days))
            ->whereNotIn('orders.order_status',['cancelled','returned'])
            ->select('order_items.product_id','order_items.product_name_snapshot',DB::raw('sum(quantity) as quantity_sold'))
            ->groupBy('order_items.product_id','order_items.product_name_snapshot')->orderByDesc('quantity_sold')->limit(5)->get();
    }
}
