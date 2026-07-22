<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brecho_transacoes', function (Blueprint $table) {
            $table->string('local_venda', 50)->default('Loja Física')->after('usuario');
        });
    }

    public function down(): void
    {
        Schema::table('brecho_transacoes', function (Blueprint $table) {
            $table->dropColumn('local_venda');
        });
    }
};
