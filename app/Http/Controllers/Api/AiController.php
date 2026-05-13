<?php

namespace App\Http\Controllers\Api;

use App\Models\AiGeneration;
use App\Models\AiInsight;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiController extends ApiController
{
    public function caption(Request $request)
    {
        $data = $request->validate(['product_name'=>['required','string'],'product_description'=>['nullable','string'],'price'=>['nullable','numeric'],'offer'=>['nullable','string'],'language'=>['nullable','in:Bangla,English,Banglish,bn,en,banglish'],'tone'=>['nullable','in:friendly,premium,funny,emotional,short']]);
        $prompt = "Write a short Facebook product caption in ".($data['language'] ?? 'Banglish').". Product name: {$data['product_name']}. Price: ".($data['price'] ?? 'N/A').". Tone: ".($data['tone'] ?? 'friendly').". Offer: ".($data['offer'] ?? 'N/A').". Include CTA and 3-5 hashtags.";
        $output = $this->generateText($prompt, $data['language'] ?? 'Banglish');
        $log = AiGeneration::create(['business_id'=>$this->business($request)->id,'user_id'=>$request->user()->id,'type'=>'caption','input_text'=>json_encode($data, JSON_UNESCAPED_UNICODE),'output_text'=>$output,'language'=>$data['language'] ?? 'Banglish','status'=>'success','created_at'=>now()]);
        return $this->ok(['caption'=>$output,'log'=>$log], 'Caption generated');
    }

    public function reply(Request $request)
    {
        $data = $request->validate(['message'=>['required','string'],'product_info'=>['nullable','string'],'language'=>['nullable','string'],'tone'=>['nullable','string']]);
        $prompt = "Write a polite short customer reply in ".($data['language'] ?? 'Banglish').". Customer asked: {$data['message']}. Product info: ".($data['product_info'] ?? 'N/A').". Tone: ".($data['tone'] ?? 'friendly').". Ask for delivery location if needed.";
        $output = $this->generateText($prompt, $data['language'] ?? 'Banglish');
        $log = AiGeneration::create(['business_id'=>$this->business($request)->id,'user_id'=>$request->user()->id,'type'=>'reply','input_text'=>json_encode($data, JSON_UNESCAPED_UNICODE),'output_text'=>$output,'language'=>$data['language'] ?? 'Banglish','status'=>'success','created_at'=>now()]);
        return $this->ok(['reply'=>$output,'short_version'=>mb_substr($output,0,120),'polite_version'=>$output,'log'=>$log], 'Reply generated');
    }

    public function history(Request $request)
    {
        return $this->ok(AiGeneration::where('business_id',$this->business($request)->id)->latest('created_at')->paginate($request->integer('per_page',20)), 'AI history');
    }

    public function insights(Request $request)
    {
        return $this->ok(AiInsight::where('business_id',$this->business($request)->id)->latest()->paginate($request->integer('per_page',20)), 'AI insights');
    }

    public function generateInsights(Request $request)
    {
        $bid = $this->business($request)->id;
        $created = [];
        $best = OrderItem::query()->join('orders','orders.id','=','order_items.order_id')
            ->where('orders.business_id',$bid)->where('orders.created_at','>=',now()->subDays(7))->whereNotIn('orders.order_status',['cancelled','returned'])
            ->select('order_items.product_id','order_items.product_name_snapshot',DB::raw('sum(quantity) as sold_qty'))
            ->groupBy('order_items.product_id','order_items.product_name_snapshot')->orderByDesc('sold_qty')->first();
        if ($best) $created[] = AiInsight::create(['business_id'=>$bid,'type'=>'best_seller','title'=>'Best-selling product','message'=>"{$best->product_name_snapshot} is your best-selling product this week with {$best->sold_qty} sold.",'severity'=>'info','data_json'=>(array)$best]);

        foreach (Product::where('business_id',$bid)->whereColumn('stock_quantity','<=','low_stock_alert')->limit(10)->get() as $p) {
            $created[] = AiInsight::create(['business_id'=>$bid,'type'=>'low_stock','title'=>'Low stock alert','message'=>"{$p->name} stock is low. Current stock: {$p->stock_quantity} {$p->unit}.",'severity'=>'warning','data_json'=>['product_id'=>$p->id]]);
        }

        foreach (Product::where('business_id',$bid)->where('stock_quantity','>',0)->whereDoesntHave('stockMovements', fn($q)=>$q->where('movement_type','order_sale')->where('created_at','>=',now()->subDays(30)))->limit(10)->get() as $p) {
            $created[] = AiInsight::create(['business_id'=>$bid,'type'=>'slow_moving','title'=>'Slow-moving product','message'=>"{$p->name} has stock but no sale in the last 30 days.",'severity'=>'info','data_json'=>['product_id'=>$p->id]]);
        }

        $repeatCustomers = Customer::where('business_id',$bid)->where('total_orders','>',2)->count();
        if ($repeatCustomers > 0) $created[] = AiInsight::create(['business_id'=>$bid,'type'=>'repeat_customer','title'=>'Repeat customers found','message'=>"You have {$repeatCustomers} repeat customers. Send them a follow-up offer.",'severity'=>'info','data_json'=>['repeat_customers'=>$repeatCustomers]]);

        $total = Order::where('business_id',$bid)->where('created_at','>=',now()->subDays(30))->count();
        $cancelled = Order::where('business_id',$bid)->where('created_at','>=',now()->subDays(30))->where('order_status','cancelled')->count();
        if ($total > 0 && ($cancelled / $total) > 0.30) $created[] = AiInsight::create(['business_id'=>$bid,'type'=>'anomaly','title'=>'High cancellation alert','message'=>'Cancellation rate is above 30% in the last 30 days. Review product, delivery, or confirmation workflow.','severity'=>'critical','data_json'=>['total'=>$total,'cancelled'=>$cancelled]]);

        AiGeneration::create(['business_id'=>$bid,'user_id'=>$request->user()->id,'type'=>'insight','input_text'=>'rule_based_business_insights','output_text'=>'Generated '.count($created).' insights','language'=>'en','status'=>'success','created_at'=>now()]);
        return $this->ok($created, 'Business insights generated');
    }

    private function generateText(string $prompt, string $language): string
    {
        $apiKey = config('shopbot.ai_api_key');
        if ($apiKey && config('shopbot.ai_endpoint')) {
            try {
                $res = Http::withToken($apiKey)->timeout(20)->post(config('shopbot.ai_endpoint'), ['prompt'=>$prompt]);
                if ($res->ok()) return data_get($res->json(), 'text') ?? data_get($res->json(), 'output') ?? $res->body();
            } catch (\Throwable $e) {
                // graceful fallback
            }
        }
        if (str_contains(strtolower($language), 'bangla') || strtolower($language)==='bn') {
            return "ধন্যবাদ! আপনার পণ্যের জন্য আকর্ষণীয় অফার চলছে। এখনই অর্ডার করুন। #ShopBotBD #OnlineShopping #Bangladesh";
        }
        return "Thanks for your interest! This product is available now. Inbox us with your delivery location to confirm total price and delivery charge. #ShopBotBD #OnlineShopping #BestDeal";
    }
}
