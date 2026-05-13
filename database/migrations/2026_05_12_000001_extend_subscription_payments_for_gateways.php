<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('subscription_id')->constrained();
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->after('plan_id');
            $table->string('merchant_invoice_number', 80)->nullable()->unique()->after('billing_cycle');
            $table->string('provider_payment_id', 180)->nullable()->index()->after('transaction_id');
            $table->string('checkout_url')->nullable()->after('provider_payment_id');
            $table->json('gateway_response')->nullable()->after('checkout_url');
            $table->string('payer_reference', 80)->nullable()->after('gateway_response');
            $table->string('bank_name', 120)->nullable()->after('payer_reference');
            $table->string('bank_account_name', 150)->nullable()->after('bank_name');
            $table->string('bank_deposit_date', 40)->nullable()->after('bank_account_name');
            $table->foreignId('approved_by')->nullable()->after('note')->constrained('users');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropUnique(['merchant_invoice_number']);
            $table->dropIndex(['provider_payment_id']);
            $table->dropConstrainedForeignId('plan_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'billing_cycle',
                'merchant_invoice_number',
                'provider_payment_id',
                'checkout_url',
                'gateway_response',
                'payer_reference',
                'bank_name',
                'bank_account_name',
                'bank_deposit_date',
                'approved_at',
                'rejected_at',
            ]);
        });
    }
};
