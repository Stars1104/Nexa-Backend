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
            // Make pagarme fields nullable
            $table->string('pagarme_customer_id')->nullable()->change();
            $table->string('pagarme_card_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            // Revert changes
            $table->string('pagarme_customer_id')->nullable(false)->change();
            $table->string('pagarme_card_id')->nullable(false)->change();
        });
    }
};
