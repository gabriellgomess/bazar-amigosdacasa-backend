<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartaoPresente extends Model
{
    use HasFactory;

    protected $table = 'cartao_presente';
    public $timestamps = false;

    protected $fillable = [
        'valor',
        'usado',
        'usado_em',
    ];

    protected $casts = [
        'usado_em' => 'datetime',
    ];
}
