<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['Free','free',0,null,50,20,1,0,['invoice'=>true,'stock'=>'basic','ai_caption'=>false,'ai_reply'=>false,'ai_insight'=>false,'support'=>'basic']],
            ['Basic','basic',499,4990,300,100,2,10,['invoice'=>true,'stock'=>true,'ai_caption'=>'limited','ai_reply'=>'limited','support'=>'standard']],
            ['Pro','pro',999,9990,1500,500,5,200,['invoice'=>true,'stock'=>true,'reports'=>true,'ai_caption'=>true,'ai_reply'=>true,'ai_insight'=>'basic','support'=>'priority']],
            ['Business','business',1999,19990,null,null,15,1000,['invoice'=>true,'stock'=>true,'advanced_reports'=>true,'ai_caption'=>true,'ai_reply'=>true,'ai_insight'=>'advanced','support'=>'priority']],
        ];
        foreach ($plans as [$name,$slug,$monthly,$yearly,$orders,$products,$staff,$ai,$features]) {
            Plan::updateOrCreate(['slug'=>$slug], ['name'=>$name,'price_monthly'=>$monthly,'price_yearly'=>$yearly,'order_limit'=>$orders,'product_limit'=>$products,'staff_limit'=>$staff,'ai_limit'=>$ai,'features_json'=>$features,'status'=>'active']);
        }
    }
}
