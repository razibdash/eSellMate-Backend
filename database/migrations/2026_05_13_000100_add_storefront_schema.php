<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('subdomain', 120)->unique();
            $table->string('store_name', 180);
            $table->text('store_description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('theme_color', 20)->default('#0f766e');
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->string('contact_number', 50)->nullable();
            $table->json('social_links')->nullable();
            $table->json('settings_json')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            $table->unique('business_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('image');
            $table->boolean('is_featured')->default(false)->after('is_published');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('public_reference', 120)->nullable()->unique()->after('invoice_number');
            $table->string('payment_method_snapshot', 50)->nullable()->after('delivery_address_snapshot');
            $table->text('customer_note')->nullable()->after('payment_method_snapshot');
            $table->json('storefront_meta')->nullable()->after('customer_note');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_payment_id', 150)->nullable()->after('transaction_id')->index();
            $table->string('order_reference', 150)->nullable()->after('provider_payment_id')->index();
            $table->string('checkout_url')->nullable()->after('order_reference');
            $table->json('gateway_response')->nullable()->after('checkout_url');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['provider_payment_id', 'order_reference', 'checkout_url', 'gateway_response']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['public_reference']);
            $table->dropColumn(['public_reference', 'payment_method_snapshot', 'customer_note', 'storefront_meta']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'is_featured']);
        });

        Schema::dropIfExists('storefronts');
    }
};
