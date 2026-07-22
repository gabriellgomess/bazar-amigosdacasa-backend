<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';
    
    // O banco de dados original não possui created_at e updated_at
    public $timestamps = false;

    protected $fillable = [
        'nome',
        'matricula',
        'usuario',
        'senha',
        'nivel_acesso',
    ];

    protected $hidden = [
        'senha',
    ];

    // O Laravel precisa saber qual coluna representa a senha
    public function getAuthPassword()
    {
        return $this->senha;
    }
}
