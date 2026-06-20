<?php
return [
    'order_sources' => ['facebook','whatsapp','instagram','phone','website','walkin','other'],
    'order_statuses' => ['pending','confirmed','processing','packed','shipped','delivered','cancelled','returned'],
    'payment_statuses' => ['unpaid','partial','paid','refunded'],
    'delivery_statuses' => ['not_assigned','ready','sent','in_transit','delivered','failed','returned'],
    'payment_methods' => ['cash','bkash','nagad','rocket','bank','card','cod','other'],
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'storefront' => [
        'root_domain' => env('STOREFRONT_ROOT_DOMAIN', 'shopbotbd.com'),
        'frontend_base_url' => env('STOREFRONT_FRONTEND_URL', env('FRONTEND_URL', 'http://localhost:3000')),
    ],
    'bank_payment' => [
        'account_name' => env('BANK_ACCOUNT_NAME'),
        'account_number' => env('BANK_ACCOUNT_NUMBER'),
        'bank_name' => env('BANK_NAME'),
        'branch_name' => env('BANK_BRANCH_NAME'),
        'routing_number' => env('BANK_ROUTING_NUMBER'),
        'instructions' => env('BANK_PAYMENT_INSTRUCTIONS'),
    ],
    'gateways' => [
        'bkash' => [
            'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
        ],
        'nagad' => [
            'base_url' => env('NAGAD_BASE_URL', 'https://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),
            'merchant_id' => env('NAGAD_MERCHANT_ID'),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
            'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY'),
            'pg_public_key' => env('NAGAD_PG_PUBLIC_KEY'),
        ],
    ],
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'sslwireless'),
        'sslwireless' => [
            'base_url' => env('SSLWIRELESS_BASE_URL', 'https://smsplus.sslwireless.com'),
            'api_token' => env('SSLWIRELESS_API_TOKEN'),
            'sid' => env('SSLWIRELESS_SID'),
        ],
    ],
    'template_variables' => ['customer_name','order_id','invoice_no','total_amount','payment_status','delivery_status','business_name'],
    'ai_api_key' => env('AI_API_KEY'),
    'ai_endpoint' => env('AI_ENDPOINT'),
];
