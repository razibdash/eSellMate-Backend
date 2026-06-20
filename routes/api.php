<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CrudController;
use App\Http\Controllers\Api\FlashSaleController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PublicStorefrontController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('plans', [SubscriptionController::class, 'plans']);

Route::prefix('payments')->group(function () {
    Route::match(['get','post'], 'bkash/callback', [PaymentCallbackController::class, 'bkash']);
    Route::match(['get','post'], 'nagad/callback', [PaymentCallbackController::class, 'nagad']);
});

Route::prefix('public')->middleware('storefront')->group(function () {
    Route::get('storefront', [PublicStorefrontController::class, 'show']);
    Route::get('products', [PublicStorefrontController::class, 'products']);
    Route::get('products/{slug}', [PublicStorefrontController::class, 'product']);
    Route::post('checkout', [PublicStorefrontController::class, 'checkout']);
    Route::get('payment-status/{reference}', [PublicStorefrontController::class, 'paymentStatus']);
});

Route::middleware('storefront')->group(function () {
    Route::get('flash-sales', [FlashSaleController::class, 'active']);
    Route::post('apply-coupon', [CouponController::class, 'apply']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('auth/profile/avatar', [AuthController::class, 'uploadAvatar']);
    Route::put('auth/password', [AuthController::class, 'changePassword']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::prefix('super-admin')->group(function () {
        Route::get('dashboard', [SuperAdminController::class, 'dashboard']);
        Route::get('businesses', [SuperAdminController::class, 'businesses']);
        Route::get('users', [SuperAdminController::class, 'users']);
        Route::get('plans', [SuperAdminController::class, 'plans']);
        Route::get('subscriptions', [SuperAdminController::class, 'subscriptions']);
        Route::get('subscription-payments', [SuperAdminController::class, 'subscriptionPayments']);
        Route::post('subscription-payments/{id}/approve', [SuperAdminController::class, 'approveSubscriptionPayment']);
        Route::post('subscription-payments/{id}/reject', [SuperAdminController::class, 'rejectSubscriptionPayment']);
        Route::get('storefronts', [SuperAdminController::class, 'storefronts']);
        Route::get('products', [SuperAdminController::class, 'products']);
        Route::get('orders', [SuperAdminController::class, 'orders']);
        Route::get('payments', [SuperAdminController::class, 'payments']);
        Route::get('reports', [SuperAdminController::class, 'reports']);
        Route::get('logs', [SuperAdminController::class, 'logs']);
        Route::put('businesses/{id}/status', [SuperAdminController::class, 'updateBusinessStatus']);
        Route::put('storefronts/{id}/status', [SuperAdminController::class, 'updateStorefrontStatus']);
        Route::delete('{resource}/{id}', [SuperAdminController::class, 'deleteResource']);
    });

    Route::middleware(['business'])->group(function () {
        Route::get('subscription', [SubscriptionController::class, 'current']);
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('permission:manage_settings');
        Route::post('subscription/payments/{id}/verify', [SubscriptionController::class, 'verify'])->middleware('permission:manage_settings');
        Route::get('subscription/invoices', [SubscriptionController::class, 'invoices'])->middleware('permission:manage_settings');

        Route::middleware(['subscription.active'])->group(function () {
        Route::get('business', [CrudController::class, 'showBusiness'])->middleware('permission:manage_settings');
        Route::put('business', [CrudController::class, 'updateBusiness'])->middleware('permission:manage_settings');
        Route::post('business/logo', [CrudController::class, 'uploadLogo'])->middleware('permission:manage_settings');
        Route::get('storefront', [StorefrontController::class, 'show'])->middleware('permission:manage_settings');
        Route::put('storefront', [StorefrontController::class, 'update'])->middleware('permission:manage_settings');
        Route::post('storefront/logo', [StorefrontController::class, 'uploadLogo'])->middleware('permission:manage_settings');
        Route::post('storefront/banner', [StorefrontController::class, 'uploadBanner'])->middleware('permission:manage_settings');
        Route::get('business/settings', [CrudController::class, 'showBusiness'])->middleware('permission:manage_settings');
        Route::put('business/settings', [CrudController::class, 'updateBusiness'])->middleware('permission:manage_settings');

        Route::get('staff', [CrudController::class, 'staff'])->middleware('permission:manage_staff');
        Route::post('staff', [CrudController::class, 'addStaff'])->middleware('permission:manage_staff');
        Route::put('staff/{id}', [CrudController::class, 'updateStaff'])->middleware('permission:manage_staff');
        Route::delete('staff/{id}', [CrudController::class, 'removeStaff'])->middleware('permission:manage_staff');
        Route::put('staff/{id}/status', [CrudController::class, 'updateStaff'])->middleware('permission:manage_staff');

        Route::get('categories', [CrudController::class, 'categories'])->middleware('permission:manage_products');
        Route::post('categories', [CrudController::class, 'createCategory'])->middleware('permission:manage_products');
        Route::put('categories/{id}', [CrudController::class, 'updateCategory'])->middleware('permission:manage_products');
        Route::delete('categories/{id}', [CrudController::class, 'deleteCategory'])->middleware('permission:manage_products');

        Route::get('products/low-stock', [CrudController::class, 'lowStock'])->middleware('permission:manage_products');
        Route::get('products', [CrudController::class, 'products'])->middleware('permission:manage_products');
        Route::post('products', [CrudController::class, 'createProduct'])->middleware('permission:manage_products');
        Route::get('products/{id}', [CrudController::class, 'product'])->middleware('permission:manage_products');
        Route::put('products/{id}', [CrudController::class, 'updateProduct'])->middleware('permission:manage_products');
        Route::delete('products/{id}', [CrudController::class, 'deleteProduct'])->middleware('permission:manage_products');
        Route::get('products/{id}/stock-movements', [CrudController::class, 'stockMovements'])->middleware('permission:manage_products');
        Route::post('products/{id}/stock-adjust', [CrudController::class, 'adjustStock'])->middleware('permission:manage_products');
        Route::post('products/{id}/image', [CrudController::class, 'updateProduct'])->middleware('permission:manage_products');
        Route::get('stock/movements', [CrudController::class, 'allStockMovements'])->middleware('permission:manage_products');
        Route::get('stock/low-stock', [CrudController::class, 'lowStock'])->middleware('permission:manage_products');

        Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:manage_products');
        Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:manage_products');
        Route::put('coupons/{id}', [CouponController::class, 'update'])->middleware('permission:manage_products');
        Route::delete('coupons/{id}', [CouponController::class, 'destroy'])->middleware('permission:manage_products');

        Route::get('flash-sales/manage', [FlashSaleController::class, 'index'])->middleware('permission:manage_products');
        Route::post('flash-sales', [FlashSaleController::class, 'store'])->middleware('permission:manage_products');
        Route::put('flash-sales/{id}', [FlashSaleController::class, 'update'])->middleware('permission:manage_products');
        Route::delete('flash-sales/{id}', [FlashSaleController::class, 'destroy'])->middleware('permission:manage_products');

        Route::get('customers', [CrudController::class, 'customers'])->middleware('permission:manage_customers');
        Route::post('customers', [CrudController::class, 'createCustomer'])->middleware('permission:manage_customers');
        Route::get('customers/{id}', [CrudController::class, 'customer'])->middleware('permission:manage_customers');
        Route::put('customers/{id}', [CrudController::class, 'updateCustomer'])->middleware('permission:manage_customers');
        Route::delete('customers/{id}', [CrudController::class, 'deleteCustomer'])->middleware('permission:manage_customers');
        Route::get('customers/{id}/orders', [CrudController::class, 'customerOrders'])->middleware('permission:manage_customers');

        Route::get('orders', [OrderController::class, 'index'])->middleware('permission:create_orders');
        Route::post('orders', [OrderController::class, 'store'])->middleware('permission:create_orders');
        Route::get('orders/{id}', [OrderController::class, 'show'])->middleware('permission:create_orders');
        Route::put('orders/{id}', [OrderController::class, 'update'])->middleware('permission:edit_orders');
        Route::delete('orders/{id}', [OrderController::class, 'destroy'])->middleware('permission:delete_orders');
        Route::put('orders/{id}/status', [OrderController::class, 'updateStatus'])->middleware('permission:edit_orders');
        Route::put('orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus'])->middleware('permission:manage_payments');
        Route::put('orders/{id}/delivery-status', [OrderController::class, 'updateDeliveryStatus'])->middleware('permission:edit_orders');
        Route::post('orders/{id}/payments', [OrderController::class, 'addPayment'])->middleware('permission:manage_payments');
        Route::post('orders/{id}/resend-sms', [OrderController::class, 'resendSms'])->middleware('permission:edit_orders');
        Route::get('orders/{id}/invoice', [OrderController::class, 'invoice'])->middleware('permission:create_orders');
        Route::post('orders/{id}/invoice/generate', [OrderController::class, 'generateInvoice'])->middleware('permission:create_orders');
        Route::get('orders/{id}/whatsapp-message', [OrderController::class, 'whatsappMessage'])->middleware('permission:create_orders');

        Route::get('message-templates', [CrudController::class, 'templates'])->middleware('permission:create_orders');
        Route::post('message-templates', [CrudController::class, 'createTemplate'])->middleware('permission:manage_settings');

        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->middleware('permission:view_reports');
        Route::get('reports/sales', [ReportController::class, 'sales'])->middleware('permission:view_reports');
        Route::get('reports/products', [ReportController::class, 'products'])->middleware('permission:view_reports');
        Route::get('reports/customers', [ReportController::class, 'customers'])->middleware('permission:view_reports');
        Route::get('reports/payments', [ReportController::class, 'payments'])->middleware('permission:view_reports');
        Route::get('reports/delivery', [ReportController::class, 'delivery'])->middleware('permission:view_reports');
        Route::get('reports/low-stock', [ReportController::class, 'lowStock'])->middleware('permission:view_reports');

        Route::post('ai/caption', [AiController::class, 'caption'])->middleware(['permission:use_ai_tools']);
        Route::post('ai/reply', [AiController::class, 'reply'])->middleware(['permission:use_ai_tools']);
        Route::get('ai/insights', [AiController::class, 'insights'])->middleware('permission:use_ai_tools');
        Route::post('ai/insights/generate', [AiController::class, 'generateInsights'])->middleware('permission:use_ai_tools');
        Route::get('ai/history', [AiController::class, 'history'])->middleware('permission:use_ai_tools');

        });
    });
});
