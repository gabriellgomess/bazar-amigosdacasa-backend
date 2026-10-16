<?php

namespace App\Services;

use App\Models\Comprador;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envia para a Trackmob, como lead, o comprador que acabou de se cadastrar no
 * bazar — tanto pelo QR Code (formulário público) quanto pelo caixa.
 *
 * Regra de ouro desta classe: NADA aqui pode derrubar o cadastro no bazar.
 * Toda falha (credencial errada, API fora do ar, CPF já existente lá) vira log
 * e o fluxo segue. Por isso os métodos públicos não lançam exceção.
 */
class TrackmobService
{
    private const CACHE_KEY_TOKEN = 'trackmob_access_token';

    /**
     * Cria o lead na Trackmob e, se houver origem, aplica a tag correspondente.
     * O $origem é o texto que vai virar tag lá ("QR Code" ou "Caixa").
     */
    public function enviarLead(Comprador $comprador, ?string $origem = null): void
    {
        if (!$this->habilitado()) {
            return;
        }

        try {
            $token = $this->accessToken();

            if (!$token) {
                return;
            }

            $resposta = $this->request($token)
                ->post($this->url('/v1/donors'), $this->montarPayload($comprador));

            if ($resposta->failed()) {
                // CPF já cadastrado na Trackmob cai aqui (decidido: só registrar
                // e seguir, sem sobrescrever o cadastro que já existe lá).
                Log::warning('Trackmob: lead nao criado.', [
                    'comprador_id' => $comprador->id,
                    'status' => $resposta->status(),
                    'resposta' => $resposta->body(),
                ]);

                return;
            }

            $donorId = $resposta->json('donorId') ?? $resposta->json('id');

            Log::info('Trackmob: lead criado.', [
                'comprador_id' => $comprador->id,
                'donor_id' => $donorId,
                'origem' => $origem,
            ]);

            if ($donorId && $origem) {
                $this->adicionarTag((string) $donorId, $origem, $token);
            }
        } catch (Exception $e) {
            Log::error('Trackmob: falha ao enviar lead: ' . $e->getMessage(), [
                'comprador_id' => $comprador->id,
            ]);
        }
    }

    /**
     * A integração só roda com tudo configurado. Sem isso ela fica inerte, o que
     * permite subir o código antes de ter as credenciais em mãos.
     */
    public function habilitado(): bool
    {
        return (bool) config('services.trackmob.enabled')
            && filled(config('services.trackmob.client_id'))
            && filled(config('services.trackmob.client_secret'))
            && filled(config('services.trackmob.campaign_id'));
    }

    /**
     * O token vale 6000s. Guarda em cache com folga para não pedir um novo a
     * cada cadastro.
     */
    private function accessToken(): ?string
    {
        $token = Cache::get(self::CACHE_KEY_TOKEN);

        if ($token) {
            return $token;
        }

        $resposta = Http::asMultipart()
            ->timeout($this->timeout())
            ->connectTimeout(3)
            ->acceptJson()
            ->post($this->url('/v1/authentication/token'), [
                'clientId' => config('services.trackmob.client_id'),
                'clientSecret' => config('services.trackmob.client_secret'),
            ]);

        if ($resposta->failed()) {
            Log::error('Trackmob: falha ao gerar access token.', [
                'status' => $resposta->status(),
                'resposta' => $resposta->body(),
            ]);

            return null;
        }

        $token = $resposta->json('accessToken');
        $expiraEm = (int) ($resposta->json('expiresIn') ?? 0);

        if (!$token) {
            return null;
        }

        // Folga de 60s para nunca usar um token prestes a vencer.
        Cache::put(self::CACHE_KEY_TOKEN, $token, $expiraEm > 120 ? $expiraEm - 60 : 300);

        return $token;
    }

    private function montarPayload(Comprador $comprador): array
    {
        $telefone = self::separarTelefone($comprador->telefone);
        $endereco = trim((string) $comprador->endereco);

        return [
            'campaignId' => config('services.trackmob.campaign_id'),
            'document' => preg_replace('/\D/', '', (string) $comprador->cpf),
            'documentType' => 'Cpf',
            'personal' => [
                'name' => $comprador->nome_completo,
                'socialName' => null,
                'birthDate' => $comprador->data_nascimento
                    ? $comprador->data_nascimento->format('Y-m-d')
                    : null,
                // O bazar não coleta gênero.
                'gender' => null,
            ],
            'company' => null,
            // O bazar guarda o endereço num único campo livre e opcional, então
            // não dá para preencher city/state/zipCode sem inventar dado.
            'address' => $endereco === '' ? null : [
                'city' => null,
                'state' => null,
                'neighbour' => null,
                'address' => $endereco,
                'number' => null,
                'zipCode' => null,
                'complement' => null,
                'reference' => null,
            ],
            'contact' => null,
            'email' => $comprador->email,
            'phones' => $telefone ? [$telefone] : null,
            'agreement' => $this->montarAgreement($comprador),
            'customFields' => null,
        ];
    }

    /**
     * Permissão de contato só vai como true quando o consentimento LGPD foi de
     * fato registrado no cadastro. Sem aceite, o lead entra sem permissão
     * nenhuma — não se presume consentimento.
     *
     * Cartas e mala direta ficam false porque o bazar não coleta endereço
     * estruturado.
     */
    private function montarAgreement(Comprador $comprador): array
    {
        $aceitou = (bool) $comprador->aceite_lgpd;

        return [
            'receiveEmails' => $aceitou,
            'receiveCalls' => $aceitou,
            'receiveSms' => $aceitou,
            'receiveWhatsApps' => $aceitou,
            'receiveLetters' => false,
            'receiveDirectMails' => false,
        ];
    }

    private function adicionarTag(string $donorId, string $tag, string $token): void
    {
        try {
            $resposta = $this->request($token)
                ->post($this->url("/v1/donors/{$donorId}/tags"), [
                    // O schema CreateDonorTagIn não está exposto na spec pública,
                    // então o nome deste campo é a melhor hipótese. Se a Trackmob
                    // recusar, o corpo do erro aparece no log abaixo e o ajuste é
                    // só nesta linha.
                    'tag' => $tag,
                ]);

            if ($resposta->failed()) {
                Log::warning('Trackmob: tag nao aplicada.', [
                    'donor_id' => $donorId,
                    'tag' => $tag,
                    'status' => $resposta->status(),
                    'resposta' => $resposta->body(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Trackmob: falha ao aplicar tag: ' . $e->getMessage(), [
                'donor_id' => $donorId,
            ]);
        }
    }

    /**
     * Quebra o telefone salvo como texto ("(51) 99707-3430") no formato que a
     * Trackmob espera. Devolve null quando não há telefone.
     */
    public static function separarTelefone(?string $telefone): ?array
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);

        if ($digitos === '') {
            return null;
        }

        // Tira o código do país quando veio junto (ex: 5551997073430).
        if (strlen($digitos) > 11 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        $tamanho = strlen($digitos);

        // 11 dígitos = DDD + celular; 10 = DDD + fixo.
        if ($tamanho === 10 || $tamanho === 11) {
            $numero = substr($digitos, 2);

            return [
                'countryCode' => '+55',
                'areaCode' => substr($digitos, 0, 2),
                'number' => $numero,
                'description' => null,
                'type' => strlen($numero) === 9 ? 'Mobile' : 'Home',
            ];
        }

        // Formato fora do padrão: manda o que tem, sem inventar DDD.
        return [
            'countryCode' => '+55',
            'areaCode' => null,
            'number' => $digitos,
            'description' => null,
            'type' => 'Mobile',
        ];
    }

    private function request(string $token)
    {
        return Http::withToken($token)
            ->timeout($this->timeout())
            ->connectTimeout(3)
            ->acceptJson();
    }

    private function timeout(): int
    {
        return (int) config('services.trackmob.timeout', 6);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.trackmob.base_url'), '/') . $path;
    }
}
