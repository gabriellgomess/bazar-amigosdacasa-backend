<?php

namespace App\Http\Controllers;

use App\Models\BazarConfiguracao;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    public function index()
    {
        $configs = BazarConfiguracao::all()->pluck('valor', 'chave');
        
        // Garantir valores padrão se não existirem no BD legado
        if (!isset($configs['permitir_cartoes_presente'])) {
            $configs['permitir_cartoes_presente'] = '1';
        }

        if (!isset($configs['permissoes_menus'])) {
            $configs['permissoes_menus'] = json_encode([
                'user' => ['/venda', '/transacoes', '/configuracoes'],
                'gerencia' => ['/venda', '/dashboard', '/backoffice', '/estoque', '/transacoes', '/configuracoes'],
                'diretoria' => ['/venda', '/dashboard', '/backoffice', '/estoque', '/transacoes', '/perfil', '/configuracoes']
            ]);
        }
        
        return response()->json($configs);
    }

    public function update(Request $request)
    {
        $request->validate([
            'permitir_venda_funcionarios' => 'required|in:0,1',
            'permitir_vouchers' => 'required|in:0,1',
            'permitir_cartoes_presente' => 'required|in:0,1',
            'valor_padrao_voucher' => 'required|numeric|min:0',
            'permissoes_menus' => 'nullable|string',
        ]);

        BazarConfiguracao::setValor('permitir_venda_funcionarios', $request->permitir_venda_funcionarios);
        BazarConfiguracao::setValor('permitir_vouchers', $request->permitir_vouchers);
        BazarConfiguracao::setValor('permitir_cartoes_presente', $request->permitir_cartoes_presente);
        BazarConfiguracao::setValor('valor_padrao_voucher', $request->valor_padrao_voucher);
        
        if ($request->has('permissoes_menus')) {
            BazarConfiguracao::setValor('permissoes_menus', $request->permissoes_menus);
        }

        return response()->json(['message' => 'Configurações salvas com sucesso!']);
    }
}
