<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estoque extends Model
{
    use HasFactory;

    protected $table = 'bazar_estoque';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'descricao',
        'tag',
        'tipo',
        'valor_loja',
        'valor_50',
        'valor_sugerido',
        'desc_func_10',
    ];
}
