<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprador extends Model
{
    use HasFactory;

    protected $table = 'bazar_compradores';

    protected $fillable = [
        'nome_completo',
        'data_nascimento',
        'cpf',
        'telefone',
        'email',
        'endereco',
        'aceite_lgpd',
        'cashback_acumulado',
        'primeira_compra_realizada',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'aceite_lgpd' => 'boolean',
        'primeira_compra_realizada' => 'boolean',
        'cashback_acumulado' => 'decimal:2',
    ];
}
