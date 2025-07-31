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
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('has_brand_review')->default(false)->after('workflow_status');
            $table->boolean('has_creator_review')->default(false)->after('has_brand_review');
            $table->boolean('has_both_reviews')->default(false)->after('has_creator_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['has_brand_review', 'has_creator_review', 'has_both_reviews']);
        });
    }
};
