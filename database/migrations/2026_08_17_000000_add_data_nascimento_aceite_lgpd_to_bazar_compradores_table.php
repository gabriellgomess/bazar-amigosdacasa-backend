<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bazar_compradores', function (Blueprint $table) {
            $table->date('data_nascimento')->nullable()->after('nome_completo');
            $table->boolean('aceite_lgpd')->default(false)->after('endereco');
        });
    }

    public function down(): void
    {
        Schema::table('bazar_compradores', function (Blueprint $table) {
            $table->dropColumn(['data_nascimento', 'aceite_lgpd']);
        });
    }
};
