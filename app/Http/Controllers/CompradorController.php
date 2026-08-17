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
        $request->validate([
            'nome_completo' => 'required|string|min:3',
            'data_nascimento' => 'required|date|before_or_equal:today',
            'cpf' => 'required|string',
            'telefone' => 'required|string',
            'email' => 'required|email',
            'endereco' => 'nullable|string',
            'aceite_lgpd' => 'required|accepted',
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
                'aceite_lgpd' => true,
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
