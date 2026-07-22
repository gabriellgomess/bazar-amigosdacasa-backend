<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Comprovante de Compra - Bazar Amigos da Casa</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,sans-serif;color:#222;">
  <div style="max-width:680px;margin:0 auto;background:#fff;padding:24px;border:1px solid #ddd;margin-top:20px;margin-bottom:20px;">
    <h2 style="margin:0 0 16px;color:#0d9488;">Recibo de Compra - Bazar Amigos da Casa</h2>
    <p>Olá, <strong>{{ $dados['nome'] }}</strong>.</p>
    <p>Agradecemos imensamente por sua compra e por apoiar a Casa de Saúde Menino Jesus de Praga através do Bazar Amigos da Casa!</p>

    <h3 style="margin-top:24px;border-bottom:2px solid #0d9488;padding-bottom:5px;">Resumo Financeiro</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
      <tr>
        <td style="padding:6px 0;width:200px;"><strong>Data da Compra:</strong></td>
        <td style="padding:6px 0;">{{ $dados['data_compra'] }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Valor Total das Peças:</strong></td>
        <td style="padding:6px 0;">R$ {{ number_format($dados['valor_bruto'], 2, ',', '.') }}</td>
      </tr>
      
      @if($dados['desconto_primeira_compra'] > 0)
      <tr>
        <td style="padding:6px 0;color:#16a34a;"><strong>Desconto Primeira Compra (10%):</strong></td>
        <td style="padding:6px 0;color:#16a34a;">- R$ {{ number_format($dados['desconto_primeira_compra'], 2, ',', '.') }}</td>
      </tr>
      @endif

      @if($dados['cashback_usado'] > 0)
      <tr>
        <td style="padding:6px 0;color:#0284c7;"><strong>Cashback Utilizado:</strong></td>
        <td style="padding:6px 0;color:#0284c7;">- R$ {{ number_format($dados['cashback_usado'], 2, ',', '.') }}</td>
      </tr>
      @endif

      @if($dados['voucher_valor'] > 0)
      <tr>
        <td style="padding:6px 0;color:#0d9488;"><strong>Voucher de Desconto:</strong></td>
        <td style="padding:6px 0;color:#0d9488;">- R$ {{ number_format($dados['voucher_valor'], 2, ',', '.') }}</td>
      </tr>
      @endif

      <tr>
        <td style="padding:6px 0;"><strong>Valor Líquido Pago:</strong></td>
        <td style="padding:6px 0;font-size:16px;font-weight:bold;color:#0d9488;">R$ {{ number_format($dados['valor_compra'], 2, ',', '.') }}</td>
      </tr>
    </table>

    <h3 style="margin-top:24px;border-bottom:2px solid #0d9488;padding-bottom:5px;">Seu Extrato de Cashback</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;background:#f8fafc;padding:12px;border:1px solid #e2e8f0;border-radius:6px;">
      <tr>
        <td style="padding:8px;width:200px;"><strong>Cashback Gerado Nesta Compra:</strong></td>
        <td style="padding:8px;color:#16a34a;font-weight:bold;">+ R$ {{ number_format($dados['cashback_gerado'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td style="padding:8px;"><strong>Saldo de Cashback Atualizado:</strong></td>
        <td style="padding:8px;font-size:16px;font-weight:bold;color:#0d9488;">R$ {{ number_format($dados['cashback_acumulado_atual'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td colspan="2" style="padding:8px;font-size:12px;color:#64748b;">
          * O cashback gerado (5%) é acumulado a partir da sua segunda compra e pode ser usado integralmente em suas próximas visitas ao bazar.
        </td>
      </tr>
    </table>

    <h3 style="margin-top:24px;border-bottom:2px solid #0d9488;padding-bottom:5px;">Itens da Venda</h3>
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="background:#f0f0f0;">
          <th style="padding:8px;text-align:left;border:1px solid #ddd;">Código</th>
          <th style="padding:8px;text-align:left;border:1px solid #ddd;">Descrição</th>
          <th style="padding:8px;text-align:center;border:1px solid #ddd;">Qtd.</th>
          <th style="padding:8px;text-align:right;border:1px solid #ddd;">Valor Unit.</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dados['itens'] as $item)
        <tr>
          <td style="padding:8px;border:1px solid #ddd;">{{ $item['codigo'] ?? '' }}</td>
          <td style="padding:8px;border:1px solid #ddd;">{{ $item['descricao'] ?? '' }}</td>
          <td style="padding:8px;border:1px solid #ddd;text-align:center;">{{ $item['quantidade'] ?? 1 }}</td>
          <td style="padding:8px;border:1px solid #ddd;text-align:right;">R$ {{ number_format($item['valor_sugerido'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div style="margin-top:40px;text-align:center;border-top:1px solid #eee;padding-top:20px;font-size:12px;color:#64748b;">
      <p><strong>Bazar Beneficente Amigos da Casa</strong></p>
      <p>Um movimento em prol da Casa de Saúde Menino Jesus de Praga</p>
      <p>Muito obrigado pela sua solidariedade!</p>
    </div>
  </div>
</body>
</html>
