<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 180)->unique()->nullable();
            $table->string('phone', 50)->unique()->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 180);
            $table->string('slug', 180)->unique();
            $table->string('logo')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 180)->nullable();
            $table->text('address')->nullable();
            $table->string('facebook_page_url')->nullable();
            $table->string('whatsapp_number', 50)->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('currency', 10)->default('BDT');
            $table->string('timezone', 80)->default('Asia/Dhaka');
            $table->string('invoice_prefix', 20)->default('SB');
            $table->text('invoice_footer')->nullable();
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 120)->unique();
            $table->string('module', 80);
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained();
            $table->enum('status', ['active', 'invited', 'inactive'])->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('users');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->integer('order_limit')->nullable();
            $table->integer('product_limit')->nullable();
            $table->integer('staff_limit')->nullable();
            $table->integer('ai_limit')->nullable();
            $table->json('features_json')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])->default('trial');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->timestamps();
        });
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method', 80)->nullable();
            $table->string('transaction_id', 150)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories');
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories');
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_alert')->default(5);
            $table->string('unit', 30)->default('pcs');
            $table->string('image')->nullable();
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'slug']);
            $table->unique(['business_id', 'sku']);
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 50);
            $table->string('email', 180)->nullable();
            $table->text('address')->nullable();
            $table->string('area', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'phone']);
        });
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address');
            $table->string('area', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('order_number', 80);
            $table->string('invoice_number', 80)->nullable();
            $table->enum('order_source', ['facebook', 'whatsapp', 'instagram', 'phone', 'website', 'walkin', 'other'])->default('facebook');
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'cancelled', 'returned'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->enum('delivery_status', ['not_assigned', 'ready', 'sent', 'in_transit', 'delivered', 'failed', 'returned'])->default('not_assigned');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->string('customer_name_snapshot', 150)->nullable();
            $table->string('customer_phone_snapshot', 50)->nullable();
            $table->text('delivery_address_snapshot')->nullable();
            $table->text('note')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'order_number']);
            $table->unique(['business_id', 'invoice_number']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('product_name_snapshot', 180);
            $table->string('sku_snapshot', 100)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'cod', 'other'])->default('cod');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('transaction_id', 150)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->string('previous_status', 80)->nullable();
            $table->string('new_status', 80);
            $table->enum('status_type', ['order', 'payment', 'delivery'])->default('order');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 80);
            $table->string('pdf_path')->nullable();
            $table->json('invoice_data_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'invoice_number']);
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('movement_type', ['opening', 'order_sale', 'order_cancel', 'return', 'restock', 'adjustment', 'damage']);
            $table->integer('quantity');
            $table->integer('previous_stock')->default(0);
            $table->integer('new_stock')->default(0);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->enum('type', ['order_confirmation', 'payment_reminder', 'delivery_update', 'custom'])->default('custom');
            $table->string('language', 20)->default('bn');
            $table->text('body');
            $table->json('variables_json')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('type', ['caption', 'reply', 'insight', 'summary']);
            $table->text('input_text')->nullable();
            $table->text('output_text')->nullable();
            $table->string('language', 20)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['best_seller', 'low_stock', 'slow_moving', 'repeat_customer', 'anomaly', 'sales_summary']);
            $table->string('title', 180);
            $table->text('message');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->json('data_json')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 100);
            $table->string('module', 100)->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('type', 80);
            $table->string('title', 180);
            $table->text('message');
            $table->json('data_json')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'audit_logs', 'ai_insights', 'ai_generations', 'message_templates', 'stock_movements', 'invoices', 'order_status_histories', 'payments', 'order_items', 'orders', 'customer_addresses', 'customers', 'product_images', 'products', 'product_categories', 'subscription_payments', 'subscriptions', 'plans', 'business_users', 'role_permissions', 'permissions', 'roles', 'businesses', 'failed_jobs', 'jobs', 'cache', 'personal_access_tokens', 'password_reset_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
