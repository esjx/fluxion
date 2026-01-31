<?php
namespace Fluxion;

use DateTime;
use Exception;

class Financeiro
{

    const MENSAL = 1;
    const BIMESTRAL = 2;
    const TRIMESTRAL = 3;
    const SEMESTRAL = 6;
    const ANUAL = 12;

    const SAC_INVERTIDO = 1;
    const SAC_NORMAL = 2;

    public static function primeiraOcorrenciaMes(string $data, int $mes, int $dif_minima = 0, int $periodicidade = self::ANUAL): string
    {

        try {

            $ano_mes = (new DateTime($data))->format('Y-m-');
            $dia = (new DateTime($data))->format('d');

            if ($dia > 25) {
                $dia = 25;
            }

            $data = $ano_mes . $dia;

            while ((new DateTime($data))->format('m') != $mes) {

                $data = (new DateTime($data))->modify('+1 month')
                    ->format('Y-m-d');

            }

            $diferenca = Util::date_diff(HOJE, $data);

            if ($diferenca['days'] < $dif_minima) {

                $data = (new DateTime($data))
                    ->modify("+$periodicidade months")
                    ->format('Y-m-d');

            }

        } catch (Exception $e) {

            Application::error($e->getMessage());

        }

        return $data;

    }

    public static function parcelas(string $data_vencimento, string $data_limite, int $primeiro_ano, float $valor_financiado, float $taxa = 0, int $periodicidade = self::ANUAL, int $regra = self::SAC_INVERTIDO, int $carencia = 0): array
    {

        $parcelas = [];

        $juros_diario = ((1 + $taxa / 100) ** ( 1 / 365)) - 1;

        try {

            $ano = 1;

            $ano_mes = (new DateTime($data_vencimento))->format('Y-m-');
            $dia = (new DateTime($data_vencimento))->format('d');

            if ($dia > 25) {
                $dia = 25;
            }

            $data_vencimento = $ano_mes . $dia;

            $ano_atual = substr($data_vencimento, 0, 4);

            while ($primeiro_ano < $ano_atual) {

                $parcelas[$ano] = [
                    'data' => $primeiro_ano . substr($data_vencimento, 4, 7),
                    'carencia' => true,
                    'valor_reembolso' => 0,
                    'cronograma' => 0,
                ];

                $primeiro_ano++;

                $ano++;

            }

            $parcelas[$ano] = [
                'data' => $data_vencimento,
                'carencia' => false,
                'valor_reembolso' => 0,
                'cronograma' => 0,
            ];

            $reembolsos = 1;

            while ($data_vencimento < $data_limite) {

                $data_vencimento = (new DateTime($data_vencimento))
                    ->modify("+$periodicidade months")
                    ->format('Y-m-d');

                if ($data_vencimento <= $data_limite) {

                    $reembolsos++;

                    $ano++;

                    $parcelas[$ano] = [
                        'data' => $data_vencimento,
                        'carencia' => false,
                        'valor_reembolso' => 0,
                        'cronograma' => 0,
                    ];

                }

            }

            $valor_reembolso = $valor_financiado / ($reembolsos - $carencia);

            $reembolso_atual = 0;

            $saldo_teorico = $valor_financiado;

            $data_anterior = HOJE;

            foreach ($parcelas as $ano => $parcela) {

                $diferenca = Util::date_diff($data_anterior, $parcela['data']);

                $parcelas[$ano]['dias'] = $diferenca['days'];

                $juros_periodo = $saldo_teorico * (((1 + $juros_diario) ** $diferenca['days']) - 1);

                $parcelas[$ano]['juros_periodo'] = $juros_periodo;

                $saldo_teorico += $juros_periodo;

                $prestacao = 0;

                if (!$parcela['carencia']) {

                    if (($ano - 1) >= $carencia) {

                        $parcelas[$ano]['valor_reembolso'] = $valor_reembolso;

                    }

                    $parcelas[$ano]['cronograma'] = 1 / ($reembolsos - $reembolso_atual);

                    if ($regra == self::SAC_INVERTIDO) {

                        $prestacao = $saldo_teorico * $parcelas[$ano]['cronograma'];

                    }

                    elseif ($regra == self::SAC_NORMAL) {

                        $prestacao = $parcelas[$ano]['valor_reembolso'] + $parcelas[$ano]['juros_periodo'];

                    }

                    else {

                        throw new CustomException('Regra de amortização inválida!');

                    }

                    $reembolso_atual++;

                }

                $saldo_teorico -= $prestacao;

                $parcelas[$ano]['prestacao'] = $prestacao;
                $parcelas[$ano]['saldo_devedor'] = $saldo_teorico;

                $data_anterior = $parcela['data'];

            }

        } catch (Exception $e) {

            Application::error($e->getMessage());

        }

        return $parcelas;

    }

    public static function taxaPeriodo($taxa, $de, $para): float
    {
        return 100 * (((1 + $taxa / 100) ** ($para / $de)) - 1);
    }

}
