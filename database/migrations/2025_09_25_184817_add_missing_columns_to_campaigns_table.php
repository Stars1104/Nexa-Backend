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
        Schema::table('campaigns', function (Blueprint $table) {
            // Add missing columns that are required by the Campaign model
            $table->decimal('final_price', 10, 2)->nullable()->after('budget');
            $table->string('location')->nullable()->after('final_price');
            $table->text('requirements')->nullable()->after('location');
            $table->json('target_states')->nullable()->after('requirements');
            $table->string('category')->nullable()->after('target_states');
            $table->string('campaign_type')->nullable()->after('category');
            $table->string('image_url')->nullable()->after('campaign_type');
            $table->date('deadline')->nullable()->after('image_url');
            $table->timestamp('approved_at')->nullable()->after('deadline');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('approved_by');
            $table->integer('max_bids')->default(10)->after('rejection_reason');
            $table->boolean('is_active')->default(true)->after('max_bids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'final_price',
                'location', 
                'requirements',
                'target_states',
                'category',
                'campaign_type',
                'image_url',
                'deadline',
                'approved_at',
                'approved_by',
                'rejection_reason',
                'max_bids',
                'is_active'
            ]);
        });
    }
};
