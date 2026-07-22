<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brecho_transacoes', function (Blueprint $table) {
            $table->unsignedBigInteger('comprador_id')->nullable()->after('id_voucher');
            $table->decimal('cashback_usado', 10, 2)->default(0.00)->after('comprador_id');
            $table->decimal('cashback_gerado', 10, 2)->default(0.00)->after('cashback_usado');
            $table->decimal('desconto_primeira_compra', 10, 2)->default(0.00)->after('cashback_gerado');
            
            $table->foreign('comprador_id')->references('id')->on('bazar_compradores')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('brecho_transacoes', function (Blueprint $table) {
            $table->dropForeign(['comprador_id']);
            $table->dropColumn(['comprador_id', 'cashback_usado', 'cashback_gerado', 'desconto_primeira_compra']);
        });
    }
};
