<?php
namespace Fluxion;

use DateTime;

$ontem = new DateTime('-1 day');
$amanha = new DateTime('+1 day');
$mais_3_dias = new DateTime('+3 days');
$um_mes_atras = new DateTime('-1 month');
$tres_meses_atras = new DateTime('-3 months');
$mais_1_mes = new DateTime('+1 month');
$mais_6_mes = new DateTime('+6 months');
$menos_1_ano = new DateTime('-1 year');
$mais_1_ano = new DateTime('+1 year');
$mais_3_anos = new DateTime('+3 years');
$mais_2_meses = new DateTime('+2 months');
$menos_30_minutos = new DateTime('-30 minutes');
$menos_1_hora = new DateTime('-1 hour');
$menos_2_horas = new DateTime('-2 hours');
$menos_5_minutos = new DateTime('-5 minutes');
$menos_1_minuto = new DateTime('-1 minute');
$uma_semana_atras = new DateTime('-1 week');
$duas_semanas_atras = new DateTime('-2 weeks');
$mais_duas_semanas = new DateTime('+2 weeks');
$mais_tres_semanas = new DateTime('+3 weeks');
$mais_dez_dias = new DateTime('+10 days');

$semestre = (date('m') <= 6) ? 1 : 2;
$semestre += date('Y') * 100;

define('SEMESTRE', $semestre);

define('ASC', 'ASC');
define('DESC', 'DESC');
define('SEGUNDOS', 1000);
define('MINUTOS', 60000);
define('HORAS', 3600000);
define('MIL', 1000);
define('MILHAO', 1000000);
define('MILHOES', 1000000);
define('BILHOES', 1000000000);
define('AGORA', date('Y-m-d H:i:s'));
define('HOJE', date('Y-m-d'));
define('ESTE_MES', date('Ym') * 1);
define('ONTEM', $ontem->format('Y-m-d'));
define('AMANHA', $amanha->format('Y-m-d'));
define('UM_MES_ATRAS', $um_mes_atras->format('Y-m-d'));
define('TRES_MESES_ATRAS', $tres_meses_atras->format('Y-m-d'));
define('MAIS_1_MES', $mais_1_mes->format('Y-m-d'));
define('MAIS_6_MESES', $mais_6_mes->format('Y-m-d'));
define('MENOS_1_ANO', $menos_1_ano->format('Y-m-d'));
define('MAIS_1_ANO', $mais_1_ano->format('Y-m-d'));
define('MAIS_2_ANOS', $mais_3_anos->format('Y-m-d'));
define('PROXIMO_MES', $mais_1_mes->format('Ym'));
define('MAIS_3_DIAS', $mais_3_dias->format('Y-m-d'));
define('MAIS_2_MESES', $mais_2_meses->format('Y-m-d'));
define('MENOS_1_MINUTO', $menos_1_minuto->format('Y-m-d H:i:s'));
define('MENOS_5_MINUTOS', $menos_5_minutos->format('Y-m-d H:i:s'));
define('MENOS_30_MINUTOS', $menos_30_minutos->format('Y-m-d H:i:s'));
define('MENOS_1_HORA', $menos_1_hora->format('Y-m-d H:i:s'));
define('MENOS_2_HORAS', $menos_2_horas->format('Y-m-d H:i:s'));
define('UMA_SEMANA_ATRAS', $uma_semana_atras->format('Y-m-d'));
define('DUAS_SEMANAS_ATRAS', $duas_semanas_atras->format('Y-m-d'));
define('MAIS_DUAS_SEMANAS', $mais_duas_semanas->format('Y-m-d'));
define('MAIS_TRES_SEMANAS', $mais_tres_semanas->format('Y-m-d'));
define('MAIS_DEZ_DIAS', $mais_dez_dias->format('Y-m-d'));

class Config2
{

    private static ?Connector\Connector2 $connector = null;

    /** @throws CustomException */
    public static function getConnector(): Connector\Connector2
    {

        if (is_null(self::$connector)) {

            if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv') {
                self::$connector = new Connector\SQLServer2();
            }

            else {
                throw new CustomException('Dados de conexão não encontrados.');
            }

        }

        return self::$connector;

    }

}
