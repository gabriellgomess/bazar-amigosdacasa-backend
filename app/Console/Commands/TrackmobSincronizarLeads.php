<?php

namespace App\Console\Commands;

use App\Models\Comprador;
use App\Services\TrackmobService;
use Illuminate\Console\Command;

/**
 * Carga retroativa: envia para a Trackmob os compradores que já estavam
 * cadastrados no bazar antes da integração existir.
 *
 * É seguro rodar mais de uma vez: antes de criar, o serviço consulta o CPF na
 * Trackmob (GET /v1/donors/id/{document}) e pula quem já está lá.
 */
class TrackmobSincronizarLeads extends Command
{
    protected $signature = 'trackmob:sincronizar-leads
        {--dry-run : Apenas mostra o que seria enviado, sem chamar a API}
        {--limit= : Processa no máximo N compradores}
        {--desde= : Só compradores cadastrados a partir desta data (AAAA-MM-DD)}
        {--tag=Retroativo : Tag aplicada aos leads; use --tag="" para não aplicar}
        {--somente-com-aceite : Pula quem não tem aceite de LGPD registrado}
        {--intervalo=200 : Pausa em milissegundos entre cada envio}';

    protected $description = 'Envia para a Trackmob os compradores já cadastrados no bazar (carga retroativa).';

    public function handle(TrackmobService $trackmob): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$trackmob->habilitado()) {
            $this->error('Integração Trackmob desligada ou sem credenciais.');
            $this->line('Confira TRACKMOB_ENABLED, TRACKMOB_CLIENT_ID e TRACKMOB_CLIENT_SECRET no .env.');

            return self::FAILURE;
        }

        $query = Comprador::query()->orderBy('id');

        if ($desde = $this->option('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }

        if ($this->option('somente-com-aceite')) {
            $query->where('aceite_lgpd', true);
        }

        if ($limite = $this->option('limit')) {
            $query->limit((int) $limite);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nenhum comprador encontrado com esses filtros.');

            return self::SUCCESS;
        }

        $semAceite = (clone $query)->where('aceite_lgpd', false)->count();
        $tag = (string) $this->option('tag');

        $this->newLine();
        $this->line("Compradores a processar: <options=bold>{$total}</>");
        $this->line('Campanha: ' . config('services.trackmob.campaign_id'));
        $this->line('Tag: ' . ($tag === '' ? '(nenhuma)' : $tag));

        if ($semAceite > 0) {
            $this->warn("{$semAceite} sem aceite de LGPD registrado — vão entrar com todos os canais de contato desligados.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('MODO DRY-RUN — nada será enviado.');
            $this->mostrarAmostra($query, $trackmob);

            return self::SUCCESS;
        }

        if (!$this->confirm('Confirma o envio para a Trackmob?', false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $intervalo = max(0, (int) $this->option('intervalo')) * 1000;
        $contagem = ['criado' => 0, 'existente' => 0, 'erro' => 0];
        $falhas = [];

        $barra = $this->output->createProgressBar($total);
        $barra->start();

        $query->chunkById(100, function ($compradores) use (
            $trackmob, $tag, $intervalo, &$contagem, &$falhas, $barra
        ) {
            foreach ($compradores as $comprador) {
                $resultado = $trackmob->sincronizar(
                    $comprador,
                    $tag === '' ? null : $tag,
                    true // consulta o CPF antes de criar
                );

                $status = $resultado['status'] === 'desabilitado' ? 'erro' : $resultado['status'];
                $contagem[$status] = ($contagem[$status] ?? 0) + 1;

                if ($status === 'erro') {
                    $falhas[] = [
                        $comprador->id,
                        $comprador->cpf,
                        mb_substr((string) $resultado['mensagem'], 0, 80),
                    ];
                }

                $barra->advance();

                if ($intervalo > 0) {
                    usleep($intervalo);
                }
            }
        });

        $barra->finish();
        $this->newLine(2);

        $this->table(
            ['Resultado', 'Qtd.'],
            [
                ['Criados na Trackmob', $contagem['criado']],
                ['Já existiam (pulados)', $contagem['existente']],
                ['Erros', $contagem['erro']],
            ]
        );

        if ($falhas !== []) {
            $this->newLine();
            $this->warn('Registros com erro (podem ser reprocessados rodando o comando de novo):');
            $this->table(['Comprador', 'CPF', 'Motivo'], array_slice($falhas, 0, 25));

            if (count($falhas) > 25) {
                $this->line('... e mais ' . (count($falhas) - 25) . '. O detalhe completo está no log do Laravel.');
            }
        }

        return $contagem['erro'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * No dry-run mostra os primeiros registros e o payload de um deles, para
     * conferência antes de disparar a carga de verdade.
     */
    private function mostrarAmostra($query, TrackmobService $trackmob): void
    {
        $amostra = (clone $query)->limit(10)->get();

        $this->table(
            ['ID', 'Nome', 'CPF', 'Nascimento', 'Aceite LGPD'],
            $amostra->map(fn ($c) => [
                $c->id,
                mb_substr((string) $c->nome_completo, 0, 35),
                $c->cpf,
                $c->data_nascimento?->format('d/m/Y') ?? '—',
                $c->aceite_lgpd ? 'sim' : 'não',
            ])->all()
        );

        if ($primeiro = $amostra->first()) {
            $this->newLine();
            $this->line("Payload que seria enviado para o comprador {$primeiro->id}:");
            $this->line(json_encode(
                $trackmob->montarPayload($primeiro),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        }
    }
}
