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
            // Add Stripe card metadata columns if they don't exist
            if (!Schema::hasColumn('brand_payment_methods', 'card_brand')) {
                $table->string('card_brand')->nullable()->after('card_holder_name');
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_last4')) {
                $table->string('card_last4')->nullable()->after('card_brand');
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_exp_month')) {
                $table->tinyInteger('card_exp_month')->nullable()->after('card_last4');
            }
            if (!Schema::hasColumn('brand_payment_methods', 'card_exp_year')) {
                $table->smallInteger('card_exp_year')->nullable()->after('card_exp_month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('brand_payment_methods', 'card_brand')) {
                $table->dropColumn('card_brand');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_last4')) {
                $table->dropColumn('card_last4');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_exp_month')) {
                $table->dropColumn('card_exp_month');
            }
            if (Schema::hasColumn('brand_payment_methods', 'card_exp_year')) {
                $table->dropColumn('card_exp_year');
            }
        });
    }
};
