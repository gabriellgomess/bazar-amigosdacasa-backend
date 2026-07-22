<?php

namespace App\Http\Controllers;

use App\Models\CartaoPresente;
use Illuminate\Http\Request;

class CartaoPresenteController extends Controller
{
    /**
     * Busca um cartão presente pelo ID/Código (usado no PDV/Venda)
     */
    public function buscaCartaoPresente(Request $request)
    {
        $request->validate([
            'id_card' => 'required|integer',
        ]);

        $idCard = intval($request->id_card);

        $cartao = CartaoPresente::find($idCard);

        if (!$cartao) {
            return response()->json([
                'success' => false,
                'message' => 'O cartão presente não foi encontrado'
            ]);
        }

        if ($cartao->usado === 1) {
            return response()->json([
                'success' => false,
                'message' => 'O cartão presente já foi usado',
                'cartao_presente' => $cartao
            ]);
        }

        return response()->json([
            'success' => true,
            'cartao_presente' => $cartao
        ]);
    }

    /**
     * Lista todos os cartões de presente (gerenciamento)
     */
    public function index(Request $request)
    {
        $query = CartaoPresente::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where('id', 'like', "%{$busca}%");
        }

        if ($request->filled('status')) {
            $status = $request->input('status'); // 'usado' ou 'disponivel'
            if ($status === 'usado') {
                $query->where('usado', 1);
            } elseif ($status === 'disponivel') {
                $query->where('usado', 0);
            }
        }

        $cartoes = $query->orderBy('id', 'desc')->paginate(15);
        return response()->json($cartoes);
    }

    /**
     * Cria um novo cartão presente
     */
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|unique:cartao_presente,id',
            'valor' => 'required|numeric|min:0',
        ], [
            'id.unique' => 'Já existe um cartão cadastrado com este código.',
            'id.required' => 'O código do cartão é obrigatório.',
            'valor.required' => 'O valor do cartão é obrigatório.'
        ]);

        $cartao = CartaoPresente::create([
            'id' => $request->id,
            'valor' => $request->valor,
            'usado' => 0,
            'usado_em' => null
        ]);

        return response()->json([
            'message' => 'Cartão presente criado com sucesso!',
            'cartao' => $cartao
        ], 201);
    }

    /**
     * Exclui um cartão presente (apenas se não tiver sido usado)
     */
    public function destroy($id)
    {
        $cartao = CartaoPresente::find($id);

        if (!$cartao) {
            return response()->json(['message' => 'Cartão presente não encontrado.'], 404);
        }

        if ($cartao->usado === 1) {
            return response()->json(['message' => 'Não é possível excluir um cartão presente que já foi utilizado.'], 400);
        }

        $cartao->delete();
        return response()->json(['message' => 'Cartão presente excluído com sucesso!']);
    }

    /**
     * Reseta ou marca o cartão presente como usado
     */
    public function toggleStatus($id)
    {
        $cartao = CartaoPresente::find($id);

        if (!$cartao) {
            return response()->json(['message' => 'Cartão presente não encontrado.'], 404);
        }

        if ($cartao->usado === 1) {
            $cartao->usado = 0;
            $cartao->usado_em = null;
        } else {
            $cartao->usado = 1;
            $cartao->usado_em = now();
        }

        $cartao->save();

        return response()->json([
            'message' => 'Status do cartão alterado com sucesso!',
            'cartao' => $cartao
        ]);
    }
}
