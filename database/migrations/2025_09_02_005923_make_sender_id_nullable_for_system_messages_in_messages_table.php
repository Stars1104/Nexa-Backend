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
        Schema::table('messages', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['sender_id']);
            
            // Make sender_id nullable
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['sender_id']);
            
            // Make sender_id not nullable again
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
