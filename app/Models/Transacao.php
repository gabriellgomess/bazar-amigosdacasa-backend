<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transacao extends Model
{
    use HasFactory;

    protected $table = 'brecho_transacoes';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'nome',
        'valor_compra',
        'total_pecas',
        'id_cartao_presente',
        'id_voucher',
        'voucher_valor',
        'parcelas',
        'forma_pagamento',
        'usuario',
        'tipo',
        'log_transacao',
        'data',
        'comprador_id',
        'cashback_usado',
        'cashback_gerado',
        'desconto_primeira_compra',
        'local_venda',
    ];

    protected $casts = [
        'log_transacao' => 'array',
        'data' => 'date',
    ];
}
