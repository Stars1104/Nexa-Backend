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
            // Rename brand_id to user_id
            $table->renameColumn('brand_id', 'user_id');
            
            // Make pagarme fields nullable
            $table->string('pagarme_customer_id')->nullable()->change();
            $table->string('pagarme_card_id')->nullable()->change();
            
            // Add card_hash field
            $table->string('card_hash')->nullable()->after('card_holder_name');
            
            // Update unique constraint
            $table->dropUnique(['brand_id', 'pagarme_card_id']);
            $table->unique(['user_id', 'card_hash']);
            
            // Update index
            $table->dropIndex(['brand_id', 'is_default']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            // Revert changes
            $table->renameColumn('user_id', 'brand_id');
            
            $table->string('pagarme_customer_id')->nullable(false)->change();
            $table->string('pagarme_card_id')->nullable(false)->change();
            
            $table->dropColumn('card_hash');
            
            $table->dropUnique(['user_id', 'card_hash']);
            $table->unique(['brand_id', 'pagarme_card_id']);
            
            $table->dropIndex(['user_id', 'is_default']);
            $table->index(['brand_id', 'is_default']);
        });
    }
};
