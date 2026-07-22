<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartaoPresenteController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\LimiteController;
use App\Http\Controllers\TransacaoController;
use App\Http\Controllers\VendaController;
use App\Http\Controllers\CompradorController;
use Illuminate\Support\Facades\Route;

// Rotas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/login.php', [AuthController::class, 'login']);
Route::post('/login/register.php', [AuthController::class, 'register']);
Route::post('/registrar_comprador', [CompradorController::class, 'registrar']);

// Rotas protegidas (Requer Autenticação via Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user-info', [AuthController::class, 'userInfo']);
    Route::get('/login/user-info.php', [AuthController::class, 'userInfo']);
    Route::get('/get_users.php', [AuthController::class, 'getUsers']);
    Route::post('/update_user.php', [AuthController::class, 'updateUser']);

    // Vendas
    Route::post('/finaliza_venda.php', [VendaController::class, 'finalizarVenda']);
    Route::post('/finaliza_venda', [VendaController::class, 'finalizarVenda']);
    Route::post('/busca_cartao_presente.php', [CartaoPresenteController::class, 'buscaCartaoPresente']);
    Route::post('/busca_cartao_presente', [CartaoPresenteController::class, 'buscaCartaoPresente']);

    // Limites de Colaboradores
    Route::post('/busca_funcionarios.php', [LimiteController::class, 'buscaFuncionarios']);
    Route::post('/busca_funcionarios', [LimiteController::class, 'buscaFuncionarios']);
    Route::post('/consulta_limites.php', [LimiteController::class, 'consultaLimites']);
    Route::post('/consulta_limites', [LimiteController::class, 'consultaLimites']);
    Route::post('/consulta_parcelas_mes.php', [LimiteController::class, 'consultaParcelasMes']);
    Route::post('/consulta_parcelas_mes', [LimiteController::class, 'consultaParcelasMes']);
    Route::post('/adiciona_funcionario.php', [LimiteController::class, 'adicionaFuncionario']);
    Route::post('/adiciona_funcionario', [LimiteController::class, 'adicionaFuncionario']);
    Route::post('/ajuste_limites.php', [LimiteController::class, 'ajusteLimites']);
    Route::post('/ajuste_limites', [LimiteController::class, 'ajusteLimites']);
    Route::post('/deletar_funcionario.php', [LimiteController::class, 'deletarFuncionario']);
    Route::post('/deletar_funcionario', [LimiteController::class, 'deletarFuncionario']);

    // Estoque / Produtos
    Route::post('/get_peca_details.php', [EstoqueController::class, 'getPecaDetails']);
    Route::post('/get_peca_details', [EstoqueController::class, 'getPecaDetails']);
    Route::post('/get_all_peca_details.php', [EstoqueController::class, 'getAllPecaDetails']);
    Route::post('/get_all_peca_details', [EstoqueController::class, 'getAllPecaDetails']);
    Route::post('/add_estoque.php', [EstoqueController::class, 'addEstoque']);
    Route::post('/add_estoque', [EstoqueController::class, 'addEstoque']);
    Route::post('/edit_estoque.php', [EstoqueController::class, 'editEstoque']);
    Route::post('/edit_estoque', [EstoqueController::class, 'editEstoque']);
    Route::post('/delete_estoque.php', [EstoqueController::class, 'deleteEstoque']);
    Route::post('/delete_estoque', [EstoqueController::class, 'deleteEstoque']);
    Route::post('/buscar_codigo.php', [EstoqueController::class, 'buscarCodigo']);
    Route::post('/buscar_codigo', [EstoqueController::class, 'buscarCodigo']);

    // Transações & Dashboard
    Route::post('/busca_transacoes.php', [TransacaoController::class, 'buscaTransacoes']);
    Route::get('/busca_transacoes.php', [TransacaoController::class, 'buscaTransacoes']);
    Route::get('/busca_transacoes', [TransacaoController::class, 'buscaTransacoes']);
    Route::post('/deletar_transacao.php', [TransacaoController::class, 'deletarTransacao']);
    Route::post('/deletar_transacao', [TransacaoController::class, 'deletarTransacao']);
    
    Route::get('/dashboard.php', [TransacaoController::class, 'dashboard']);
    Route::get('/dashboard', [TransacaoController::class, 'dashboard']);
    Route::get('/sales_last_10.php', [TransacaoController::class, 'salesLast10']);
    Route::get('/sales_last_10', [TransacaoController::class, 'salesLast10']);
    Route::get('/sales_months.php', [TransacaoController::class, 'salesMonths']);
    Route::get('/sales_months', [TransacaoController::class, 'salesMonths']);

    // Importações / Modelos
    Route::post('/importar_limites.php', [ImportacaoController::class, 'importarLimites']);
    Route::post('/importar_limites', [ImportacaoController::class, 'importarLimites']);
    Route::get('/modelos/modelo_importacao_limites.php', [ImportacaoController::class, 'downloadModelo']);
    Route::get('/modelos/modelo_importacao_limites', [ImportacaoController::class, 'downloadModelo']);

    // Configurações Gerais
    Route::get('/configuracoes', [ConfiguracaoController::class, 'index']);
    Route::post('/configuracoes', [ConfiguracaoController::class, 'update']);

    // Gerenciamento de Cartões Presentes
    Route::get('/cartoes_presentes', [CartaoPresenteController::class, 'index']);
    Route::post('/cartoes_presentes', [CartaoPresenteController::class, 'store']);
    Route::delete('/cartoes_presentes/{id}', [CartaoPresenteController::class, 'destroy']);
    Route::post('/cartoes_presentes/{id}/toggle-status', [CartaoPresenteController::class, 'toggleStatus']);

    // Gerenciamento de Vouchers
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::post('/vouchers', [VoucherController::class, 'store']);
    Route::delete('/vouchers/{id}', [VoucherController::class, 'destroy']);
    Route::post('/vouchers/{id}/toggle-status', [VoucherController::class, 'toggleStatus']);
    Route::post('/valida_voucher', [VoucherController::class, 'validaVoucher']);
    Route::post('/busca_comprador', [CompradorController::class, 'buscarPorCpf']);
});
