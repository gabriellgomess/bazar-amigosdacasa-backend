<?php

namespace App\Http\Controllers;

use App\Models\Limite;
use App\Models\LogAjusteLimite;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacaoController extends Controller
{
    private function normalizarCabecalho($value)
    {
        $value = strtolower(trim((string) $value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }

    private function indiceColuna($headers, $names)
    {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }
        return false;
    }

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

    public function importarLimites(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'usuario' => 'required|string',
        ]);

        $usuario = $request->usuario;
        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, false, false);

            $headerRow = array_shift($rows);
            $headers = array_map([$this, 'normalizarCabecalho'], $headerRow ?? []);

            $colNome = $this->indiceColuna($headers, ['nome']);
            $colEmail = $this->indiceColuna($headers, ['email']);
            $colSalario = $this->indiceColuna($headers, ['salario']);
            $colLimiteTotal = $this->indiceColuna($headers, ['limite_total']);
            $colLimiteDisponivel = $this->indiceColuna($headers, ['limite_disponivel']);
            $colLimiteValorParcela = $this->indiceColuna($headers, ['limite_valor_parcela', 'limite_parcela']);

            if ($colNome === false || $colSalario === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A planilha deve conter pelo menos as colunas nome e salario.'
                ], 422);
            }

            $registrosImportados = 0;

            DB::beginTransaction();

            // Limpa todos os limites atuais
            Limite::truncate();

            foreach ($rows as $row) {
                $nome = trim((string) ($row[$colNome] ?? ''));
                if ($nome === '') {
                    continue;
                }

                $email = $colEmail !== false ? (trim((string) ($row[$colEmail] ?? '')) ?: null) : null;
                $salario = $this->valorDecimal($row[$colSalario] ?? null) ?? 0;
                $limiteTotal = $colLimiteTotal !== false ? $this->valorDecimal($row[$colLimiteTotal] ?? null) : null;
                $limiteDisponivel = $colLimiteDisponivel !== false ? $this->valorDecimal($row[$colLimiteDisponivel] ?? null) : null;
                $limiteValorParcela = $colLimiteValorParcela !== false ? $this->valorDecimal($row[$colLimiteValorParcela] ?? null) : null;

                if ($limiteTotal === null) {
                    $limiteTotal = $salario * 0.2;
                }

                if ($limiteDisponivel === null) {
                    $limiteDisponivel = $limiteTotal;
                }

                Limite::create([
                    'nome' => $nome,
                    'email' => $email,
                    'salario' => (string) $salario,
                    'limite_total' => $limiteTotal,
                    'limite_disponivel' => $limiteDisponivel,
                    'limite_valor_parcela' => $limiteValorParcela,
                    'inserido' => now(),
                ]);

                $registrosImportados++;
            }

            $log = "Importação de limites realizada por $usuario. $registrosImportados registros importados.";
            LogAjusteLimite::create([
                'nome' => $usuario,
                'limite_disponivel' => '0',
                'limite_total' => '0',
                'usuario' => $usuario,
                'acao' => $log,
                'data' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Importação concluída com sucesso! ($registrosImportados registros)",
                'registros' => $registrosImportados
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Erro na importação de limites: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar o arquivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadModelo()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modelo Importacao Limites');

        $headers = ['nome', 'email', 'salario', 'limite_total', 'limite_disponivel', 'limite_valor_parcela'];
        $sheet->fromArray($headers, null, 'A1');

        $data = [
            ['ADRIANA DE OLIVEIRA MACHADO', 'adriana@email.com', 2861.25, 572.25, 572.25, 200.00],
            ['ALCIONES DESSOY BACHENDORF', 'alciones@email.com', 5348.70, 1069.74, 838.78, 350.00],
            ['ALERSON PAREDES ALVES', 'alerson@email.com', 2893.80, 578.76, 578.76, 190.00],
        ];
        $sheet->fromArray($data, null, 'A2');

        // Estilização do cabeçalho
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Estilização dos dados
        $sheet->getStyle('A2:F4')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Definindo largura das colunas
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(22);

        // Formatação numérica
        $sheet->getStyle('C2:F4')->getNumberFormat()->setFormatCode('#,##0.00');

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="modelo_importacao_limites.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
