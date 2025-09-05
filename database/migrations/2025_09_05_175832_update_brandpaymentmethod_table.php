<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            // Rename brand_id -> user_id
            if (Schema::hasColumn('brand_payment_methods', 'brand_id')) {
                $table->renameColumn('brand_id', 'user_id');
            }

            // Drop old Pagarme-specific fields if they exist
            if (Schema::hasColumn('brand_payment_methods', 'pagarme_customer_id')) {
                $table->dropColumn('pagarme_customer_id');
            }
            if (Schema::hasColumn('brand_payment_methods', 'pagarme_card_id')) {
                $table->dropColumn('pagarme_card_id');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_brand')) {
                $table->dropColumn('card_brand');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_last4')) {
                $table->dropColumn('card_last4');
            }

            // Add new Stripe-specific fields if not exist
            if (!Schema::hasColumn('brand_payment_methods', 'customer_id')) {
                $table->string('customer_id')->index()->after('user_id');
            }
            if (!Schema::hasColumn('brand_payment_methods', 'payment_method_id')) {
                $table->string('payment_method_id')->index()->after('customer_id');
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_holder_name')) {
                $table->string('card_holder_name')->nullable()->after('payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            // Rollback: rename user_id back to brand_id
            if (Schema::hasColumn('brand_payment_methods', 'user_id')) {
                $table->renameColumn('user_id', 'brand_id');
            }

            // Restore old Pagarme fields
            if (!Schema::hasColumn('brand_payment_methods', 'pagarme_customer_id')) {
                $table->string('pagarme_customer_id')->nullable();
            }
            if (!Schema::hasColumn('brand_payment_methods', 'pagarme_card_id')) {
                $table->string('pagarme_card_id')->nullable();
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_brand')) {
                $table->string('card_brand')->nullable();
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_last4')) {
                $table->string('card_last4')->nullable();
            }

            // Drop Stripe-specific fields
            if (Schema::hasColumn('brand_payment_methods', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('brand_payment_methods', 'payment_method_id')) {
                $table->dropColumn('payment_method_id');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_holder_name')) {
                $table->dropColumn('card_holder_name');
            }
        });
    }
};
