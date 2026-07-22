<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $table = 'vouchers';

    protected $fillable = [
        'codigo',
        'valor',
        'ativo',
        'usado',
        'usado_em',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'usado' => 'boolean',
        'usado_em' => 'datetime',
    ];
}
