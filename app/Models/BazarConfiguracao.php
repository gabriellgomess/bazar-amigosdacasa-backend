<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BazarConfiguracao extends Model
{
    use HasFactory;

    protected $table = 'bazar_configuracoes';

    protected $fillable = [
        'chave',
        'valor',
    ];

    /**
     * Helper to get a config value by key.
     */
    public static function getValor($chave, $default = null)
    {
        $config = self::where('chave', $chave)->first();
        return $config ? $config->valor : $default;
    }

    /**
     * Helper to set/update a config value.
     */
    public static function setValor($chave, $valor)
    {
        return self::updateOrCreate(
            ['chave' => $chave],
            ['valor' => (string) $valor]
        );
    }
}
