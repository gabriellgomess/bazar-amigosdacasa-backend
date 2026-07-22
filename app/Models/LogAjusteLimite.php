<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAjusteLimite extends Model
{
    use HasFactory;

    protected $table = 'brecho_logs_ajustes_limites';
    public $timestamps = false;

    protected $fillable = [
        'nome',
        'limite_disponivel',
        'limite_total',
        'usuario',
        'acao',
        'data',
    ];

    protected $casts = [
        'data' => 'datetime',
    ];
}
