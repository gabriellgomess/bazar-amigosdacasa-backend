<?php

namespace App\Http\Controllers;

use App\Models\Estoque;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EstoqueController extends Controller
{
    public function getPecaDetails(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
        ]);

        $peca = Estoque::where('codigo', $request->codigo)->first();

        if ($peca) {
            return response()->json([
                'status' => 'success',
                'peca' => $peca
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Não foi possível encontrar a peça'
        ], 404);
    }

    public function getAllPecaDetails()
    {
        $pecas = Estoque::orderBy('codigo', 'desc')->get();

        if ($pecas->isNotEmpty()) {
            return response()->json([
                'status' => 'success',
                'pecas' => $pecas
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Não foi possível encontrar as peças'
        ], 404);
    }

    public function addEstoque(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'descricao' => 'required|string',
            'tag' => 'required|string',
            'tipo' => 'required|string',
            'valor_sugerido' => 'required|numeric',
            'valor_desconto' => 'required|numeric', // Mapeado para desc_func_10 no banco
        ]);

        try {
            $existe = Estoque::where('codigo', $request->codigo)->exists();
            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto com este código já existe.'
                ], 422);
            }

            Estoque::create([
                'codigo' => $request->codigo,
                'descricao' => $request->descricao,
                'tag' => $request->tag,
                'tipo' => $request->tipo,
                'valor_sugerido' => $request->valor_sugerido,
                'desc_func_10' => $request->valor_desconto,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto adicionado com sucesso.'
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao adicionar produto: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao adicionar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function editEstoque(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'descricao' => 'required|string',
            'tag' => 'required|string',
            'tipo' => 'required|string',
            'valor_sugerido' => 'required|numeric',
            'desc_func_10' => 'required|numeric',
        ]);

        try {
            $peca = Estoque::where('codigo', $request->codigo)->first();

            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado.'
                ], 404);
            }

            $peca->update([
                'descricao' => $request->descricao,
                'tag' => $request->tag,
                'tipo' => $request->tipo,
                'valor_sugerido' => $request->valor_sugerido,
                'desc_func_10' => $request->desc_func_10,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto editado com sucesso.'
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao editar produto: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteEstoque(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
        ]);

        try {
            $peca = Estoque::where('codigo', $request->codigo)->first();

            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado.'
                ], 404);
            }

            $peca->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produto deletado com sucesso.'
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao deletar produto: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function buscarCodigo()
    {
        // Encontra o maior código numérico na tabela e incrementa em 1
        $maxCodigo = DB::table('bazar_estoque')
            ->selectRaw('MAX(CAST(codigo AS UNSIGNED)) as max_codigo')
            ->value('max_codigo');

        $codigo = sprintf('%06d', ($maxCodigo ?? 0) + 1);

        return response()->json($codigo);
    }
}
