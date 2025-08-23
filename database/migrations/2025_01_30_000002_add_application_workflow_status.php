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
        Schema::table('campaign_applications', function (Blueprint $table) {
            // Add workflow status to track the progression of creator-brand interaction
            $table->enum('workflow_status', [
                'first_contact_pending',    // Application approved, waiting for first contact
                'agreement_in_progress',    // Chat started, negotiation happening
                'agreement_finalized'       // Contract/agreement reached
            ])->default('first_contact_pending')->after('status');
            
            // Add timestamp for when first contact was initiated
            $table->timestamp('first_contact_at')->nullable()->after('workflow_status');
            
            // Add timestamp for when agreement was finalized
            $table->timestamp('agreement_finalized_at')->nullable()->after('first_contact_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_applications', function (Blueprint $table) {
            $table->dropColumn(['workflow_status', 'first_contact_at', 'agreement_finalized_at']);
        });
    }
}; 