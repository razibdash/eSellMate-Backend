<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(['email'=>'owner@shopbotbd.test'], ['name'=>'ShopBot Owner','phone'=>'01700000001','password'=>Hash::make('password'),'status'=>'active','is_super_admin'=>true]);
        $business = Business::updateOrCreate(['slug'=>'fresh-achar-house'], ['owner_id'=>$owner->id,'name'=>'Fresh Achar House','phone'=>'01700000001','email'=>'fresh@example.com','address'=>'Dhaka, Bangladesh','facebook_page_url'=>'https://facebook.com/freshachar','whatsapp_number'=>'8801700000001','currency'=>'BDT','timezone'=>'Asia/Dhaka','invoice_prefix'=>'FAH','status'=>'active']);
        $role = Role::where('code','owner')->first();
        if ($role) $business->users()->syncWithoutDetaching([$owner->id=>['role_id'=>$role->id,'status'=>'active','joined_at'=>now(),'created_at'=>now(),'updated_at'=>now()]]);
        $plan = Plan::where('slug','pro')->first() ?: Plan::first();
        if ($plan) Subscription::updateOrCreate(['business_id'=>$business->id,'plan_id'=>$plan->id], ['status'=>'active','starts_at'=>now(),'ends_at'=>now()->addMonth(),'billing_cycle'=>'monthly']);

        $cat = ProductCategory::updateOrCreate(['business_id'=>$business->id,'slug'=>'pickles'], ['name'=>'Pickles','description'=>'Homemade achar items','status'=>'active']);
        $items = [
            ['Garlic Pickle','GP-001',250,30], ['Olive Pickle','OP-001',220,15], ['Mango Pickle','MP-001',300,8], ['Chili Pickle','CP-001',180,20]
        ];
        $products = [];
        foreach ($items as [$name,$sku,$price,$stock]) {
            $p = Product::updateOrCreate(['business_id'=>$business->id,'sku'=>$sku], ['category_id'=>$cat->id,'name'=>$name,'slug'=>Str::slug($name),'price'=>$price,'cost_price'=>$price * .6,'stock_quantity'=>$stock,'low_stock_alert'=>10,'unit'=>'jar','status'=>'active']);
            $products[] = $p;
            StockMovement::firstOrCreate(['business_id'=>$business->id,'product_id'=>$p->id,'movement_type'=>'opening','reference_type'=>'seed'], ['quantity'=>$stock,'previous_stock'=>0,'new_stock'=>$stock,'created_by'=>$owner->id,'created_at'=>now()]);
        }

        for ($i=1; $i<=20; $i++) {
            Customer::updateOrCreate(['business_id'=>$business->id,'phone'=>'018000000'.str_pad((string)$i,2,'0',STR_PAD_LEFT)], ['name'=>'Demo Customer '.$i,'address'=>'Dhaka Area '.$i,'city'=>'Dhaka','status'=>'active']);
        }

        if (Order::where('business_id',$business->id)->count() === 0) {
            $customers = Customer::where('business_id',$business->id)->get();
            for ($i=1; $i<=50; $i++) {
                $customer = $customers->random();
                $product = collect($products)->random();
                $qty = rand(1,3);
                $subtotal = $product->price * $qty;
                $delivery = 60;
                $total = $subtotal + $delivery;
                $paid = rand(0,1) ? $total : 0;
                $status = ['pending','confirmed','processing','packed','shipped','delivered'][array_rand(['pending','confirmed','processing','packed','shipped','delivered'])];
                $order = Order::create(['business_id'=>$business->id,'customer_id'=>$customer->id,'created_by'=>$owner->id,'order_number'=>'ORD-'.now()->format('Y').'-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'invoice_number'=>'FAH-'.now()->format('Y').'-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'order_source'=>['facebook','whatsapp','instagram','phone'][array_rand(['facebook','whatsapp','instagram','phone'])],'order_status'=>$status,'payment_status'=>$paid ? 'paid' : 'unpaid','delivery_status'=>$status === 'delivered' ? 'delivered' : 'not_assigned','subtotal'=>$subtotal,'delivery_charge'=>$delivery,'total_amount'=>$total,'paid_amount'=>$paid,'due_amount'=>$total-$paid,'customer_name_snapshot'=>$customer->name,'customer_phone_snapshot'=>$customer->phone,'delivery_address_snapshot'=>$customer->address,'confirmed_at'=>in_array($status,['confirmed','processing','packed','shipped','delivered']) ? now() : null,'delivered_at'=>$status==='delivered' ? now() : null,'created_at'=>now()->subDays(rand(0,30))]);
                OrderItem::create(['order_id'=>$order->id,'product_id'=>$product->id,'product_name_snapshot'=>$product->name,'sku_snapshot'=>$product->sku,'unit_price'=>$product->price,'quantity'=>$qty,'discount_amount'=>0,'line_total'=>$subtotal]);
                if ($paid > 0) Payment::create(['business_id'=>$business->id,'order_id'=>$order->id,'payment_method'=>['cash','bkash','nagad','cod'][array_rand(['cash','bkash','nagad','cod'])],'amount'=>$paid,'payment_status'=>'paid','paid_at'=>$order->created_at,'created_by'=>$owner->id]);
            }
            foreach ($customers as $customer) {
                $orders = $customer->orders()->whereNotIn('order_status',['cancelled','returned']);
                $customer->update(['total_orders'=>(clone $orders)->count(),'total_spent'=>(clone $orders)->sum('paid_amount'),'last_order_at'=>(clone $orders)->latest()->value('created_at')]);
            }
        }
    }
}
