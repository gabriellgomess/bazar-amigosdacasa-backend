<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Valida um código de voucher (usado no PDV/Venda)
     */
    public function validaVoucher(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
        ]);

        $codigo = trim($request->codigo);
        $voucher = Voucher::where('codigo', $codigo)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'O voucher não existe.'
            ]);
        }

        if (!$voucher->ativo) {
            return response()->json([
                'success' => false,
                'message' => 'O voucher está inativo.'
            ]);
        }

        if ($voucher->usado) {
            return response()->json([
                'success' => false,
                'message' => 'O voucher já foi utilizado.'
            ]);
        }

        return response()->json([
            'success' => true,
            'voucher' => $voucher
        ]);
    }

    /**
     * Lista todos os vouchers (gerenciamento)
     */
    public function index(Request $request)
    {
        $query = Voucher::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where('codigo', 'like', "%{$busca}%");
        }

        if ($request->filled('status')) {
            $status = $request->input('status'); // 'usado', 'ativo', 'inativo'
            if ($status === 'usado') {
                $query->where('usado', true);
            } elseif ($status === 'ativo') {
                $query->where('ativo', true)->where('usado', false);
            } elseif ($status === 'inativo') {
                $query->where('ativo', false);
            }
        }

        $vouchers = $query->orderBy('id', 'desc')->paginate(15);
        return response()->json($vouchers);
    }

    /**
     * Cria um novo voucher
     */
    public function store(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|unique:vouchers,codigo',
            'valor' => 'required|numeric|min:0',
        ], [
            'codigo.unique' => 'Já existe um voucher cadastrado com este código.',
            'codigo.required' => 'O código do voucher é obrigatório.',
            'valor.required' => 'O valor do voucher é obrigatório.'
        ]);

        $voucher = Voucher::create([
            'codigo' => trim($request->codigo),
            'valor' => $request->valor,
            'ativo' => true,
            'usado' => false,
            'usado_em' => null
        ]);

        return response()->json([
            'message' => 'Voucher criado com sucesso!',
            'voucher' => $voucher
        ], 201);
    }

    /**
     * Exclui um voucher (apenas se não tiver sido usado)
     */
    public function destroy($id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json(['message' => 'Voucher não encontrado.'], 404);
        }

        if ($voucher->usado) {
            return response()->json(['message' => 'Não é possível excluir um voucher que já foi utilizado.'], 400);
        }

        $voucher->delete();
        return response()->json(['message' => 'Voucher excluído com sucesso!']);
    }

    /**
     * Ativa/Desativa o voucher
     */
    public function toggleStatus($id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json(['message' => 'Voucher não encontrado.'], 404);
        }

        $voucher->ativo = !$voucher->ativo;
        $voucher->save();

        return response()->json([
            'message' => 'Status do voucher alterado com sucesso!',
            'voucher' => $voucher
        ]);
    }
}
