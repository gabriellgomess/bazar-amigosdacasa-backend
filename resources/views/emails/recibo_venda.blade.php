<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Compra registrada no Bazar Amigos da Casa</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Arial,sans-serif;color:#222;">
  <div style="max-width:680px;margin:0 auto;background:#fff;padding:24px;border:1px solid #ddd;margin-top:20px;margin-bottom:20px;">
    <h2 style="margin:0 0 16px;color:#19725D;">Compra registrada no Bazar Amigos da Casa</h2>
    <p>Olá, <strong>{{ $dados['nome'] }}</strong>.</p>
    <p>Sua compra com desconto em folha foi registrada com sucesso.</p>

    <h3 style="margin-top:24px;border-bottom:2px solid #19725D;padding-bottom:5px;">Dados da Compra</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
      <tr>
        <td style="padding:6px 0;width:150px;"><strong>Data:</strong></td>
        <td style="padding:6px 0;">{{ $dados['data_compra'] }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Valor total:</strong></td>
        <td style="padding:6px 0;">R$ {{ number_format($dados['valor_compra'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Parcelas:</strong></td>
        <td style="padding:6px 0;">{{ $dados['quantidade_parcelas'] }}x de R$ {{ number_format($dados['valor_parcela'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Total de peças:</strong></td>
        <td style="padding:6px 0;">{{ $dados['total_pecas'] }}</td>
      </tr>
    </table>

    <h3 style="margin-top:24px;border-bottom:2px solid #19725D;padding-bottom:5px;">Limite de Crédito</h3>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
      <tr>
        <td style="padding:6px 0;width:150px;"><strong>Limite total:</strong></td>
        <td style="padding:6px 0;">R$ {{ number_format($dados['limite_total'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Limite antes da compra:</strong></td>
        <td style="padding:6px 0;">R$ {{ number_format($dados['limite_anterior'], 2, ',', '.') }}</td>
      </tr>
      <tr>
        <td style="padding:6px 0;"><strong>Limite disponível atual:</strong></td>
        <td style="padding:6px 0;color:#d9534f;font-weight:bold;">R$ {{ number_format($dados['limite_atual'], 2, ',', '.') }}</td>
      </tr>
      @if($dados['limite_valor_parcela'] > 0)
      <tr>
        <td style="padding:6px 0;"><strong>Limite por parcela:</strong></td>
        <td style="padding:6px 0;">R$ {{ number_format($dados['limite_valor_parcela'], 2, ',', '.') }}</td>
      </tr>
      @endif
    </table>

    <h3 style="margin-top:24px;border-bottom:2px solid #19725D;padding-bottom:5px;">Itens da Venda</h3>
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
          <td style="padding:8px;border:1px solid #ddd;text-align:right;">R$ {{ number_format($item['valor_pago'] ?? $item['valor_sugerido'] ?? 0, 2, ',', '.') }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</body>
</html>
