<?php

namespace App\Http\Controllers;

use App\Models\Comprador;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompradorController extends Controller
{
    public function buscarPorCpf(Request $request)
    {
        $request->validate([
            'cpf' => 'required|string',
        ]);

        $cpfLimpo = preg_replace('/[^0-9]/', '', $request->cpf);

        $comprador = Comprador::where('cpf', $cpfLimpo)->first();

        if ($comprador) {
            return response()->json([
                'success' => true,
                'comprador' => $comprador
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Comprador não encontrado.'
        ], 404);
    }

    public function registrar(Request $request)
    {
        // Data de nascimento e aceite da LGPD são obrigatórios no formulário
        // público (/cadastro-cliente), onde a validação é feita na tela. Aqui
        // ficam opcionais porque o cadastro rápido do caixa (modal da tela de
        // Venda) não coleta esses dados.
        $request->validate([
            'nome_completo' => 'required|string|min:3',
            'data_nascimento' => 'nullable|date|before_or_equal:today',
            'cpf' => 'required|string',
            'telefone' => 'required|string',
            'email' => 'required|email',
            'endereco' => 'nullable|string',
            'aceite_lgpd' => 'nullable|boolean',
        ]);

        $cpfLimpo = preg_replace('/[^0-9]/', '', $request->cpf);

        if (strlen($cpfLimpo) !== 11) {
            return response()->json([
                'success' => false,
                'message' => 'O CPF informado deve conter exatamente 11 dígitos.'
            ], 422);
        }

        try {
            // Verificar se o CPF já está cadastrado
            $existe = Comprador::where('cpf', $cpfLimpo)->exists();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este CPF já está cadastrado como comprador.'
                ], 422);
            }

            $comprador = Comprador::create([
                'nome_completo' => trim($request->nome_completo),
                'data_nascimento' => $request->data_nascimento,
                'cpf' => $cpfLimpo,
                'telefone' => trim($request->telefone),
                'email' => trim($request->email),
                'endereco' => trim((string) $request->endereco),
                // Registra o aceite real: só fica true quando o comprador de fato
                // marcou o consentimento (formulário público). Nunca presumir o
                // aceite em cadastro feito por terceiros.
                'aceite_lgpd' => $request->boolean('aceite_lgpd'),
                'cashback_acumulado' => 0.00,
                'primeira_compra_realizada' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comprador cadastrado com sucesso!',
                'comprador' => $comprador
            ], 201);

        } catch (Exception $e) {
            Log::error("Erro ao registrar comprador: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar o cadastro: ' . $e->getMessage()
            ], 500);
        }
    }
}
