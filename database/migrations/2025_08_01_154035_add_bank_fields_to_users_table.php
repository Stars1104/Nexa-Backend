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
        Schema::table('users', function (Blueprint $table) {
            // Bank account fields
            $table->string('bank_code', 4)->nullable()->after('account_id');
            $table->string('agencia', 5)->nullable()->after('bank_code');
            $table->string('agencia_dv', 2)->nullable()->after('agencia');
            $table->string('conta', 12)->nullable()->after('agencia_dv');
            $table->string('conta_dv', 2)->nullable()->after('conta');
            $table->string('cpf', 14)->nullable()->after('conta_dv'); // Format: XXX.XXX.XXX-XX
            $table->string('bank_account_name', 255)->nullable()->after('cpf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bank_code',
                'agencia',
                'agencia_dv',
                'conta',
                'conta_dv',
                'cpf',
                'bank_account_name'
            ]);
        });
    }
};
