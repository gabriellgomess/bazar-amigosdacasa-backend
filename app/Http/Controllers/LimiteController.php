<?php

namespace App\Http\Controllers;

use App\Models\Limite;
use App\Models\LogAjusteLimite;
use App\Models\Transacao;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LimiteController extends Controller
{
    private function valorDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim(str_replace(['R$', ' '], '', $value));

            if (str_contains($value, ',')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
                $value = str_replace('.', '', $value);
            }
        }

        return is_numeric($value) ? floatval($value) : null;
    }

    public function buscaFuncionarios()
    {
        $funcionarios = Limite::orderBy('nome', 'asc')->get();
        return response()->json($funcionarios);
    }

    public function consultaLimites(Request $request)
    {
        $request->validate([
            'nome_funcionario' => 'required|string'
        ]);

        $limite = Limite::where('nome', $request->nome_funcionario)->first();

        if (!$limite) {
            return response()->json([
                'success' => false,
                'message' => 'Funcionário não encontrado.'
            ], 404);
        }

        return response()->json($limite);
    }

    public function adicionaFuncionario(Request $request)
    {
        $request->validate([
            'nome' => 'required|string',
            'email' => 'nullable|email',
            'salario' => 'required',
            'usuario' => 'required|string',
        ]);

        $nome = trim($request->nome);
        $email = $request->email ? trim($request->email) : null;
        $salario = $this->valorDecimal($request->salario);
        $usuario = trim($request->usuario);

        if ($salario === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Salário inválido.'
            ], 422);
        }

        // Se limites não forem informados, calcular
        $limiteTotal = $this->valorDecimal($request->limite_total ?? $request->limite ?? null);
        if ($limiteTotal === null) {
            $limiteTotal = $salario * 0.2;
        }

        $limiteDisponivel = $this->valorDecimal($request->limite_disponivel ?? $request->limite ?? null);
        if ($limiteDisponivel === null) {
            $limiteDisponivel = $limiteTotal;
        }

        $limiteValorParcela = $this->valorDecimal($request->limite_valor_parcela ?? null);

        try {
            DB::beginTransaction();

            $existe = Limite::where('nome', $nome)->exists();
            if ($existe) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Funcionário já cadastrado.'
                ], 422);
            }

            $limite = Limite::create([
                'nome' => $nome,
                'email' => $email,
                'salario' => (string) $salario,
                'limite_total' => $limiteTotal,
                'limite_disponivel' => $limiteDisponivel,
                'limite_valor_parcela' => $limiteValorParcela,
                'inserido' => now(),
            ]);

            $acao = "Funcionario adicionado com limite total de R$ " . number_format($limiteTotal, 2, ',', '.');
            
            LogAjusteLimite::create([
                'nome' => $nome,
                'limite_disponivel' => (string) $limiteDisponivel,
                'limite_total' => (string) $limiteTotal,
                'usuario' => $usuario,
                'acao' => $acao,
                'data' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Funcionário adicionado com sucesso.'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao adicionar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ajusteLimites(Request $request)
    {
        $request->validate([
            'nome' => 'required|string',
            'user' => 'required|string',
            'new_limite_disponivel' => 'required',
            'new_limite_total' => 'required',
        ]);

        $nome = $request->nome;
        $usuario = $request->user;
        $novoEmail = $request->new_email ? trim($request->new_email) : null;
        $novoLimiteDisponivel = $this->valorDecimal($request->new_limite_disponivel);
        $novoLimiteTotal = $this->valorDecimal($request->new_limite_total);
        $novoLimiteValorParcela = $request->new_limite_valor_parcela !== '' ? $this->valorDecimal($request->new_limite_valor_parcela) : null;

        if ($novoLimiteDisponivel === null || $novoLimiteTotal === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Limite disponível e limite total são obrigatórios.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $limite = Limite::where('nome', $nome)->lockForUpdate()->first();
            if (!$limite) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Funcionário não encontrado.'
                ], 404);
            }

            $limiteDisponivelAntigo = floatval($limite->limite_disponivel);
            $limiteTotalAntigo = floatval($limite->limite_total);

            $diferencaDisponivel = $novoLimiteDisponivel - $limiteDisponivelAntigo;
            $diferencaTotal = $novoLimiteTotal - $limiteTotalAntigo;

            $statusDisponivel = match(true) {
                $diferencaDisponivel > 0 => "Limite disponível aumentou {$diferencaDisponivel}",
                $diferencaDisponivel < 0 => "Limite disponível diminuiu {$diferencaDisponivel}",
                default                   => "Limite disponível manteve"
            };

            $statusTotal = match(true) {
                $diferencaTotal > 0 => "Limite total aumentou {$diferencaTotal}",
                $diferencaTotal < 0 => "Limite total diminuiu {$diferencaTotal}",
                default              => "Limite total manteve"
            };

            $statusLimites = $statusDisponivel . " e " . $statusTotal;

            $limite->update([
                'email' => $novoEmail,
                'limite_disponivel' => $novoLimiteDisponivel,
                'limite_total' => $novoLimiteTotal,
                'limite_valor_parcela' => $novoLimiteValorParcela,
            ]);

            LogAjusteLimite::create([
                'nome' => $nome,
                'limite_disponivel' => (string) $novoLimiteDisponivel,
                'limite_total' => (string) $novoLimiteTotal,
                'usuario' => $usuario,
                'acao' => $statusLimites,
                'data' => now(),
            ]);

            DB::commit();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao ajustar limites: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deletarFuncionario(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'nome' => 'required|string',
            'user' => 'required|string'
        ]);

        $id = $request->id;
        $nome = $request->nome;
        $usuario = $request->user;

        try {
            DB::beginTransaction();

            $limite = Limite::where('id', $id)->where('nome', $nome)->first();

            if (!$limite) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Funcionário não encontrado.'
                ], 404);
            }

            $limite->delete();

            $acao = "Excluiu o funcionário {$nome} (ID: {$id}) do sistema de limites.";

            LogAjusteLimite::create([
                'nome' => $nome,
                'limite_disponivel' => '',
                'limite_total' => '',
                'usuario' => $usuario,
                'acao' => $acao,
                'data' => now(),
            ]);

            DB::commit();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao remover funcionário: ' . $e->getMessage()
            ], 500);
        }
    }

    public function consultaParcelasMes(Request $request)
    {
        $request->validate([
            'nome_funcionario' => 'required|string'
        ]);

        $nome = $request->nome_funcionario;

        // Busca transações de Desconto em Folha do funcionário dos últimos 3 meses
        $transacoes = Transacao::where('nome', $nome)
            ->where('forma_pagamento', 'Desconto em Folha')
            ->where('data', '>=', now()->subMonths(3)->toDateString())
            ->get();

        $parcelasPorMes = [];
        $mesAtual = new DateTime('first day of this month');

        foreach ($transacoes as $transacao) {
            $numParcelas = max(1, intval($transacao->parcelas));
            $valorParcela = floatval($transacao->valor_compra) / $numParcelas;
            $dataCompra = new DateTime($transacao->data->toDateString());

            for ($i = 1; $i <= $numParcelas; $i++) {
                $mesParcela = clone $dataCompra;
                $mesParcela->modify("+{$i} month");
                $mesParcela->modify('first day of this month');

                // Considera apenas meses presentes ou futuros
                if ($mesParcela >= $mesAtual) {
                    $chave = $mesParcela->format('m/Y');
                    $parcelasPorMes[$chave] = ($parcelasPorMes[$chave] ?? 0) + $valorParcela;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'parcelas_por_mes' => $parcelasPorMes
        ]);
    }
}
