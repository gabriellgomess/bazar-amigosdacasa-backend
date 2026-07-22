<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bazar_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        // Inserir valores padrão
        DB::table('bazar_configuracoes')->insert([
            ['chave' => 'permitir_venda_funcionarios', 'valor' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'permitir_vouchers', 'valor' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'valor_padrao_voucher', 'valor' => '150.00', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('bazar_configuracoes');
    }
};
