<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $vars = ['customer_name','order_id','invoice_no','total_amount','payment_status','delivery_status','business_name'];
        MessageTemplate::updateOrCreate(['business_id'=>null,'title'=>'Order Confirmation EN'], ['type'=>'order_confirmation','language'=>'en','body'=>'Hello {customer_name}, your order {invoice_no} has been confirmed. Total amount: {total_amount}. Thank you for shopping with {business_name}.','variables_json'=>$vars,'status'=>'active']);
        MessageTemplate::updateOrCreate(['business_id'=>null,'title'=>'Order Confirmation BN'], ['type'=>'order_confirmation','language'=>'bn','body'=>'প্রিয় {customer_name}, আপনার অর্ডার {invoice_no} কনফার্ম করা হয়েছে। মোট বিল: {total_amount}. ধন্যবাদ।','variables_json'=>$vars,'status'=>'active']);
        MessageTemplate::updateOrCreate(['business_id'=>null,'title'=>'Payment Reminder'], ['type'=>'payment_reminder','language'=>'banglish','body'=>'Hello {customer_name}, apnar order {invoice_no} er due amount ache. Payment status: {payment_status}.','variables_json'=>$vars,'status'=>'active']);
        MessageTemplate::updateOrCreate(['business_id'=>null,'title'=>'Delivery Update'], ['type'=>'delivery_update','language'=>'banglish','body'=>'Hello {customer_name}, apnar order {invoice_no} delivery status: {delivery_status}.','variables_json'=>$vars,'status'=>'active']);
    }
}
