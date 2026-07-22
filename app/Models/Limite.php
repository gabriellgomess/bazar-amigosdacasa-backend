<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Limite extends Model
{
    use HasFactory;

    protected $table = 'brecho_limites';
    public $timestamps = false;

    protected $fillable = [
        'nome',
        'email',
        'salario',
        'limite_total',
        'limite_disponivel',
        'limite_valor_parcela',
        'inserido',
    ];
}
