<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazar_compradores', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 150);
            $table->string('cpf', 14)->unique();
            $table->string('telefone', 20);
            $table->string('email', 100);
            $table->string('endereco', 255)->nullable();
            $table->decimal('cashback_acumulado', 10, 2)->default(0.00);
            $table->boolean('primeira_compra_realizada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bazar_compradores');
    }
};
