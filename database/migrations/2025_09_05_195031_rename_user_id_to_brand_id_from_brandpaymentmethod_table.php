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
            if (Schema::hasColumn('brand_payment_methods', 'user_id')) {
                $table->renameColumn('user_id', 'brand_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brand_payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('brand_payment_methods', 'brand_id')) {
                $table->renameColumn('brand_id', 'user_id');
            }
        });
    }
};
