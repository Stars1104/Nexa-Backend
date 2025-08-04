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
        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // bank_transfer, pix, pagarme_account
            $table->string('name'); // Transferência Bancária, PIX, etc.
            $table->text('description');
            $table->decimal('min_amount', 10, 2);
            $table->decimal('max_amount', 10, 2);
            $table->string('processing_time'); // "2-3 dias úteis", "Até 24 horas"
            $table->decimal('fee', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->json('required_fields')->nullable(); // Fields required for this method
            $table->json('field_config')->nullable(); // Configuration for form fields
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_methods');
    }
};
