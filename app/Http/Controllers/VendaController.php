<?php

namespace App\Http\Controllers;

use App\Models\CartaoPresente;
use App\Models\Limite;
use App\Models\Transacao;
use App\Models\BazarConfiguracao;
use App\Models\Voucher;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VendaController extends Controller
{
    public function finalizarVenda(Request $request)
    {
        $request->validate([
            'request_id' => 'required|string',
            'nome_funcionario' => 'nullable|string',
            'data_compra' => 'required|date',
            'valor_compra' => 'required|numeric',
            'total_pecas' => 'required|integer',
            'quantidade_parcelas' => 'required|integer',
            'valor_parcela' => 'required|numeric',
            'forma_pagamento' => 'required|string',
            'id_cartao_presente' => 'nullable|integer',
            'id_voucher' => 'nullable|integer',
            'voucher_codigo' => 'nullable|string',
            'voucher_valor' => 'nullable|numeric',
            'log_transacao' => 'required|array',
            'check_func' => 'required|integer',
            'comprador_cpf' => 'nullable|string',
            'use_cashback' => 'nullable|boolean',
            'desconto_primeira_compra' => 'nullable|numeric',
            'cashback_usado' => 'nullable|numeric',
            'local_venda' => 'nullable|string',
        ]);

        $requestId = trim($request->request_id);

        // 1. Checagem rápida de idempotência
        $transacaoExistente = Transacao::where('request_id', $requestId)->first();
        if ($transacaoExistente) {
            return response()->json([
                'success' => true,
                'message' => 'Venda já processada anteriormente.',
                'idempotent' => true,
                'request_id' => $requestId,
                'email_enviado' => false,
                'email_erro' => null,
            ]);
        }

        // Definir tipo de venda
        $formaPagamento = $request->forma_pagamento;
        $checkFunc = $request->check_func;
        $nomeFuncionario = $request->nome_funcionario;

        if ($formaPagamento === "Desconto em Folha" || $checkFunc == 1) {
            $tipo = "funcionario";
        } elseif ($formaPagamento === "Acolhido") {
            $tipo = "acolhido";
            $nomeFuncionario = "Nome do acolhido";
        } else {
            $tipo = "externo";
            $nomeFuncionario = "Venda externa";
        }

        // 1.1. Verificar se a venda para funcionários está habilitada nas configurações
        if ($tipo === "funcionario") {
            $permitirVendaFunc = BazarConfiguracao::getValor('permitir_venda_funcionarios', '1');
            if ($permitirVendaFunc !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'Venda para colaboradores está temporariamente desativada nas configurações.'
                ], 400);
            }
        }

        // 1.2. Verificar se o uso de vouchers está habilitado se um voucher for fornecido
        $voucherCodigo = trim($request->voucher_codigo ?? '');
        $isUsandoVoucher = !empty($voucherCodigo) || intval($request->id_voucher) > 0 || floatval($request->voucher_valor) > 0;
        
        if ($isUsandoVoucher) {
            $permitirVoucher = BazarConfiguracao::getValor('permitir_vouchers', '1');
            if ($permitirVoucher !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'O uso de vouchers está temporariamente desativado nas configurações.'
                ], 400);
            }
        }

        // 1.3. Verificar se o uso de cartões presentes está habilitado
        $idCartaoPresenteReq = intval($request->id_cartao_presente);
        if ($idCartaoPresenteReq > 0) {
            $permitirCartao = BazarConfiguracao::getValor('permitir_cartoes_presente', '1');
            if ($permitirCartao !== '1') {
                return response()->json([
                    'success' => false,
                    'message' => 'O uso de cartões presentes está temporariamente desativado nas configurações.'
                ], 400);
            }
        }

        $emailEnviado = false;
        $emailErro = null;
        $dadosEmail = null;
        $voucherIdCalculado = $request->id_voucher;
        $voucherValorCalculado = $request->voucher_valor;

        try {
            DB::beginTransaction();

            // 2. Processar Voucher pelo Banco (se fornecido via código)
            if (!empty($voucherCodigo)) {
                $voucher = Voucher::where('codigo', $voucherCodigo)
                    ->where('usado', false)
                    ->where('ativo', true)
                    ->lockForUpdate()
                    ->first();

                if (!$voucher) {
                    throw new Exception("Voucher inválido, inativo ou já utilizado.");
                }

                $voucher->usado = true;
                $voucher->usado_em = $request->data_compra;
                $voucher->save();

                $voucherIdCalculado = $voucher->id;
                $voucherValorCalculado = $voucher->valor;
            }

            // 3. Processar Cartão Presente (se houver)
            $idCartaoPresente = intval($request->id_cartao_presente);
            if ($idCartaoPresente > 0) {
                $cartao = CartaoPresente::where('id', $idCartaoPresente)
                    ->where('usado', 0)
                    ->lockForUpdate()
                    ->first();

                if (!$cartao) {
                    throw new Exception("Cartão presente já foi usado ou não foi encontrado.");
                }

                $cartao->usado = 1;
                $cartao->usado_em = $request->data_compra;
                $cartao->save();
            }

            // 4. Processar Limite de Colaborador (Desconto em Folha)
            if ($formaPagamento === "Desconto em Folha") {
                // Bloqueio pessimista para evitar concorrência desordenada no limite
                $limite = Limite::where('nome', $nomeFuncionario)
                    ->lockForUpdate()
                    ->first();

                if (!$limite) {
                    throw new Exception("Funcionário não encontrado para atualização de limite.");
                }

                $novoLimiteDisponivel = floatval($limite->limite_disponivel) - floatval($request->valor_compra);

                $limite->limite_disponivel = $novoLimiteDisponivel;
                $limite->save();

                // Preparar dados do e-mail
                if (!empty($limite->email) && filter_var($limite->email, FILTER_VALIDATE_EMAIL)) {
                    $dadosEmail = [
                        'nome' => $nomeFuncionario,
                        'email' => $limite->email,
                        'data_compra' => date('d/m/Y', strtotime($request->data_compra)),
                        'valor_compra' => $request->valor_compra,
                        'total_pecas' => $request->total_pecas,
                        'quantidade_parcelas' => $request->quantidade_parcelas,
                        'valor_parcela' => $request->valor_parcela,
                        'limite_total' => $limite->limite_total,
                        'limite_anterior' => $limite->limite_disponivel + $request->valor_compra,
                        'limite_atual' => $novoLimiteDisponivel,
                        'limite_valor_parcela' => $limite->limite_valor_parcela,
                        'itens' => $request->log_transacao,
                    ];
                } else {
                    $emailErro = 'Funcionário sem e-mail válido cadastrado.';
                }
            }

            // 4.2. Processar Comprador / Cashback (Se CPF for fornecido e tipo for externo)
            $compradorId = null;
            $cashbackUsado = 0.00;
            $cashbackGerado = 0.00;
            $descontoPrimeiraCompra = 0.00;
            $compradorCpf = trim($request->comprador_cpf ?? '');

            if ($tipo === "externo" && !empty($compradorCpf)) {
                $cpfLimpo = preg_replace('/[^0-9]/', '', $compradorCpf);
                $comprador = \App\Models\Comprador::where('cpf', $cpfLimpo)
                    ->lockForUpdate()
                    ->first();

                if ($comprador) {
                    $compradorId = $comprador->id;
                    $descontoPrimeiraCompra = floatval($request->desconto_primeira_compra ?? 0.00);
                    $cashbackUsado = floatval($request->cashback_usado ?? 0.00);

                    if (!$comprador->primeira_compra_realizada) {
                        $comprador->primeira_compra_realizada = true;
                        $comprador->save();
                    } else {
                        if ($cashbackUsado > 0) {
                            $comprador->cashback_acumulado = 0.00; // Zera conforme regra
                        }
                        
                        $valorLiquidoPago = floatval($request->valor_compra);
                        $cashbackGerado = round($valorLiquidoPago * 0.05, 2);
                        $comprador->cashback_acumulado += $cashbackGerado;
                        $comprador->save();
                    }

                    // Preparar dados do e-mail do comprador
                    if (!empty($comprador->email) && filter_var($comprador->email, FILTER_VALIDATE_EMAIL)) {
                        $dadosEmail = [
                            'is_comprador' => true,
                            'nome' => $comprador->nome_completo,
                            'email' => $comprador->email,
                            'data_compra' => date('d/m/Y', strtotime($request->data_compra)),
                            'valor_bruto' => $request->valor_compra + $descontoPrimeiraCompra + $cashbackUsado,
                            'valor_compra' => $request->valor_compra,
                            'desconto_primeira_compra' => $descontoPrimeiraCompra,
                            'cashback_usado' => $cashbackUsado,
                            'cashback_gerado' => $cashbackGerado,
                            'cashback_acumulado_atual' => $comprador->cashback_acumulado,
                            'voucher_valor' => $voucherValorCalculado ?? 0.00,
                            'itens' => $request->log_transacao,
                        ];
                    }
                }
            }

            // 5. Criar Transação
            $transacao = Transacao::create([
                'request_id' => $requestId,
                'nome' => $nomeFuncionario,
                'valor_compra' => (string) $request->valor_compra,
                'total_pecas' => (string) $request->total_pecas,
                'id_cartao_presente' => $request->id_cartao_presente ?? 0,
                'id_voucher' => $voucherIdCalculado,
                'voucher_valor' => $voucherValorCalculado,
                'parcelas' => $request->quantidade_parcelas,
                'forma_pagamento' => $formaPagamento,
                'usuario' => $request->usuario ?? 'Sistema',
                'tipo' => $tipo,
                'log_transacao' => $request->log_transacao,
                'data' => $request->data_compra,
                'comprador_id' => $compradorId,
                'cashback_usado' => $cashbackUsado,
                'cashback_gerado' => $cashbackGerado,
                'desconto_primeira_compra' => $descontoPrimeiraCompra,
                'local_venda' => $request->local_venda ?? 'Loja Física',
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Erro na transação de venda: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        // 6. Enviar E-mail (Fora da Transação para evitar lentidão no DB)
        if ($dadosEmail !== null) {
            try {
                $template = isset($dadosEmail['is_comprador']) ? 'emails.recibo_comprador' : 'emails.recibo_venda';
                Mail::send($template, ['dados' => $dadosEmail], function ($message) use ($dadosEmail) {
                    $message->to($dadosEmail['email'], $dadosEmail['nome'])
                            ->subject('Compra registrada no Bazar Amigos da Casa');
                });
                $emailEnviado = true;
            } catch (Exception $e) {
                Log::error("Erro ao enviar e-mail do bazar: " . $e->getMessage());
                $emailErro = $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Venda realizada com sucesso.',
            'idempotent' => false,
            'request_id' => $requestId,
            'email_enviado' => $emailEnviado,
            'email_erro' => $emailErro,
        ]);
    }
}
