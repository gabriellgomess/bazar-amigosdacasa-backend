<?php

namespace App\Http\Controllers;

use App\Models\Limite;
use App\Models\LogAjusteLimite;
use App\Models\Transacao;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransacaoController extends Controller
{
    public function buscaTransacoes(Request $request)
    {
        $query = Transacao::query()
            ->select(
                'brecho_transacoes.*', 
                'cartao_presente.valor as cartao_presente_valor', 
                'cartao_presente.usado as cartao_presente_usado', 
                'cartao_presente.usado_em as cartao_presente_usado_em',
                'bazar_compradores.nome_completo as comprador_nome',
                'bazar_compradores.cpf as comprador_cpf'
            )
            ->leftJoin('cartao_presente', 'brecho_transacoes.id_cartao_presente', '=', 'cartao_presente.id')
            ->leftJoin('bazar_compradores', 'brecho_transacoes.comprador_id', '=', 'bazar_compradores.id');

        // Filtro por data
        if ($request->has('data_inicio') && $request->has('data_fim')) {
            $query->whereBetween('brecho_transacoes.data', [$request->data_inicio, $request->data_fim]);
        }

        // Filtro por nome ou CPF
        if ($request->filled('nome')) {
            $search = trim($request->nome);
            $cleanCpf = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $cleanCpf) {
                $q->where('brecho_transacoes.nome', 'like', '%' . $search . '%')
                  ->orWhere('bazar_compradores.nome_completo', 'like', '%' . $search . '%');
                
                if (!empty($cleanCpf)) {
                    $q->orWhere('bazar_compradores.cpf', 'like', '%' . $cleanCpf . '%');
                }
            });
        }

        // Filtro por tipo
        if ($request->filled('tipo')) {
            $query->where('brecho_transacoes.tipo', $request->tipo);
        }

        // Filtro por forma de pagamento
        if ($request->filled('forma_pagamento')) {
            $query->where('brecho_transacoes.forma_pagamento', $request->forma_pagamento);
        }

        // Filtro por Local de Venda
        if ($request->filled('local_venda')) {
            $query->where('brecho_transacoes.local_venda', $request->local_venda);
        }

        // Filtro por Operador (usuario)
        if ($request->filled('usuario')) {
            $query->where('brecho_transacoes.usuario', 'like', '%' . $request->usuario . '%');
        }

        // Filtro por benefício / desconto
        if ($request->filled('beneficio')) {
            switch ($request->beneficio) {
                case 'qualquer':
                    $query->where(function ($q) {
                        $q->where('brecho_transacoes.id_voucher', '>', 0)
                          ->orWhere('brecho_transacoes.id_cartao_presente', '>', 0)
                          ->orWhere('brecho_transacoes.cashback_usado', '>', 0)
                          ->orWhere('brecho_transacoes.desconto_primeira_compra', '>', 0);
                    });
                    break;
                case 'voucher':
                    $query->where('brecho_transacoes.id_voucher', '>', 0);
                    break;
                case 'cartao_presente':
                    $query->where('brecho_transacoes.id_cartao_presente', '>', 0);
                    break;
                case 'cashback':
                    $query->where('brecho_transacoes.cashback_usado', '>', 0);
                    break;
                case 'desconto_1_compra':
                    $query->where('brecho_transacoes.desconto_primeira_compra', '>', 0);
                    break;
            }
        }

        $transacoes = $query->orderBy('brecho_transacoes.id', 'desc')->get();

        // Mapeamento de formatos de dados compatíveis com o front original
        $transacoes = $transacoes->map(function ($t) {
            $data = $t->toArray();
            if ($t->data) {
                $data['data'] = $t->data->format('d/m/Y');
            }
            $data['valor_compra'] = floatval($t->valor_compra);
            $data['cartao_presente_valor'] = $t->cartao_presente_valor ? floatval($t->cartao_presente_valor) : null;
            $data['total_pecas'] = intval($t->total_pecas);
            $data['cashback_usado'] = floatval($t->cashback_usado ?? 0.00);
            $data['cashback_gerado'] = floatval($t->cashback_gerado ?? 0.00);
            $data['desconto_primeira_compra'] = floatval($t->desconto_primeira_compra ?? 0.00);
            return $data;
        });

        return response()->json($transacoes);
    }

    public function deletarTransacao(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'usuario' => 'required|string',
            'nivel_acesso' => 'required|string',
        ]);

        $nivelAcesso = strtolower(trim($request->nivel_acesso));

        if ($nivelAcesso !== 'diretoria') {
            return response()->json([
                'status' => 'error',
                'message' => 'Apenas usuários da diretoria podem excluir transações.'
            ], 403);
        }

        $transactionId = intval($request->id);
        $usuario = trim($request->usuario);

        try {
            DB::beginTransaction();

            $transacao = Transacao::lockForUpdate()->find($transactionId);

            if (!$transacao) {
                throw new Exception("Transação não encontrada.");
            }

            $nome = $transacao->nome;
            $valor = floatval($transacao->valor_compra);
            $devolveuLimite = false;

            // Exclui a transação
            $transacao->delete();

            // Se for desconto em folha de funcionário, devolve o limite
            if ($transacao->forma_pagamento === 'Desconto em Folha' && $transacao->tipo === 'funcionario') {
                $limite = Limite::where('nome', $nome)->lockForUpdate()->first();
                if ($limite) {
                    $limite->limite_disponivel = floatval($limite->limite_disponivel) + $valor;
                    $limite->save();
                    $devolveuLimite = true;
                }
            }

            // Registra em logs de auditoria
            $acao = "Excluiu a transação ID $transactionId no valor de R$ " . number_format($valor, 2, ',', '.') .
                " do usuário $nome, ação executada por $usuario.";

            if ($devolveuLimite) {
                $acao .= " Limite devolvido ao funcionário.";
            }

            LogAjusteLimite::create([
                'nome' => $nome,
                'usuario' => $usuario,
                'acao' => $acao,
                'data' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $devolveuLimite
                    ? 'Transação excluída e limite devolvido.'
                    : 'Transação excluída com sucesso.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Erro ao deletar transação: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function dashboard(Request $request)
    {
        $dataInicio = $request->get('data_inicio', now()->subDays(30)->toDateString());
        $dataFim = $request->get('data_fim', now()->toDateString());

        // Últimas 5 transações
        $ultimasTransacoes = Transacao::orderBy('data', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // Estatísticas totais (geral)
        $estatisticasTotaisRaw = DB::table('brecho_transacoes')
            ->selectRaw('COUNT(DISTINCT nome) as total_clientes, SUM(CAST(valor_compra AS DECIMAL(10,2))) as total_vendas, SUM(CAST(total_pecas AS UNSIGNED)) as total_pecas, AVG(CAST(valor_compra AS DECIMAL(10,2))) as media_venda, COUNT(*) as total_transacoes')
            ->first();

        // Estatísticas filtradas por período
        $estatisticasFiltradasRaw = DB::table('brecho_transacoes')
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->selectRaw('COUNT(DISTINCT nome) as total_clientes, SUM(CAST(valor_compra AS DECIMAL(10,2))) as total_vendas, SUM(CAST(total_pecas AS UNSIGNED)) as total_pecas, AVG(CAST(valor_compra AS DECIMAL(10,2))) as media_venda, COUNT(*) as total_transacoes')
            ->first();

        // Vendas por dia no período
        $vendasDia = DB::table('brecho_transacoes')
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->selectRaw('data, SUM(CAST(valor_compra AS DECIMAL(10,2))) as valor_total, SUM(CAST(total_pecas AS UNSIGNED)) as total_pecas, COUNT(*) as total_transacoes')
            ->groupBy('data')
            ->orderBy('data', 'asc')
            ->get();

        // Formas de pagamento no período
        $totalTransacoesPeriodo = DB::table('brecho_transacoes')
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->count();

        $formasPagamento = [];
        if ($totalTransacoesPeriodo > 0) {
            $formasPagamento = DB::table('brecho_transacoes')
                ->whereBetween('data', [$dataInicio, $dataFim])
                ->selectRaw('forma_pagamento, COUNT(*) as total, SUM(CAST(valor_compra AS DECIMAL(10,2))) as valor_total, (COUNT(*) * 100.0 / ?) as percentual', [$totalTransacoesPeriodo])
                ->groupBy('forma_pagamento')
                ->get();
        }

        // Categorias (Tags) mais vendidas
        $transacoesPeriodo = Transacao::whereBetween('data', [$dataInicio, $dataFim])
            ->select('log_transacao')
            ->get();

        $categorias = [];
        foreach ($transacoesPeriodo as $transacao) {
            $itens = $transacao->log_transacao;
            if (is_array($itens)) {
                foreach ($itens as $item) {
                    $tag = $item['tag'] ?? 'Sem Tag';
                    $qtd = intval($item['quantidade'] ?? 1);
                    $categorias[$tag] = ($categorias[$tag] ?? 0) + $qtd;
                }
            }
        }

        // Top 3 Clientes Funcionários por valor no período
        $topClientes = DB::table('brecho_transacoes')
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->where('tipo', 'funcionario')
            ->selectRaw('nome, SUM(CAST(valor_compra AS DECIMAL(10,2))) as total_compras, SUM(CAST(total_pecas AS UNSIGNED)) as total_pecas, COUNT(*) as total_transacoes')
            ->groupBy('nome')
            ->orderBy('total_compras', 'desc')
            ->limit(3)
            ->get();

        return response()->json([
            'parametros' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ],
            'transacoes' => $ultimasTransacoes,
            'estatisticas_totais' => [
                'total_clientes' => intval($estatisticasTotaisRaw->total_clientes ?? 0),
                'total_vendas' => floatval($estatisticasTotaisRaw->total_vendas ?? 0),
                'total_pecas' => intval($estatisticasTotaisRaw->total_pecas ?? 0),
                'media_venda' => floatval($estatisticasTotaisRaw->media_venda ?? 0),
                'total_transacoes' => intval($estatisticasTotaisRaw->total_transacoes ?? 0)
            ],
            'estatisticas_filtradas' => [
                'total_clientes' => intval($estatisticasFiltradasRaw->total_clientes ?? 0),
                'total_vendas' => floatval($estatisticasFiltradasRaw->total_vendas ?? 0),
                'total_pecas' => intval($estatisticasFiltradasRaw->total_pecas ?? 0),
                'media_venda' => floatval($estatisticasFiltradasRaw->media_venda ?? 0),
                'total_transacoes' => intval($estatisticasFiltradasRaw->total_transacoes ?? 0)
            ],
            'vendas_dia' => $vendasDia,
            'formas_pagamento' => $formasPagamento,
            'categorias' => $categorias,
            'top_clientes' => $topClientes
        ]);
    }

    public function salesLast10()
    {
        $vendas = DB::table('brecho_transacoes')
            ->selectRaw("SUM(CAST(valor_compra AS DECIMAL(10,2))) as total_vendas_reais, SUM(CAST(total_pecas AS UNSIGNED)) as total_pecas, DATE_FORMAT(data, '%d/%m/%Y') as data_formatada, data")
            ->groupBy('data_formatada', 'data')
            ->orderBy('data', 'desc')
            ->limit(10)
            ->get();

        $vendasMapped = $vendas->map(function ($v) {
            return [
                'total_vendas_reais' => floatval($v->total_vendas_reais),
                'data' => $v->data_formatada,
                'total_pecas' => intval($v->total_pecas)
            ];
        });

        return response()->json($vendasMapped);
    }

    public function salesMonths()
    {
        $vendasMes = DB::table('brecho_transacoes')
            ->selectRaw('YEAR(data) as ano, MONTH(data) as mes, SUM(CAST(valor_compra AS DECIMAL(10,2))) as totalCompra')
            ->groupBy('ano', 'mes')
            ->orderBy('ano', 'desc')
            ->orderBy('mes', 'desc')
            ->get();

        $vendasMesMapped = $vendasMes->map(function ($v) {
            return [
                'ano' => intval($v->ano),
                'mes' => intval($v->mes),
                'totalCompra' => floatval($v->totalCompra)
            ];
        });

        return response()->json($vendasMesMapped);
    }
}
