<?php
namespace Fluxion;

use DateTime;

class Config
{

    private $_cost_center_id = 5551;
    private $_sub_cost_center_id = 5400;
    private $_digital_id = 1;
    private $_title = 'Title';
    private $_base_href = 'https://www.example.com/';
    private $_page_not_found = 'index.html';
    private $_page_error = 'error.html';
    private $_email = 'TEST <test@example.com>';

    private $_connectors = [];

    private $_domain_list = [];

    public function __construct($environment_file = null)
    {

        if ($environment_file) {
            (new Environment())->load($environment_file);
        }

        define('DOMPDF_ENABLE_AUTOLOAD', $_ENV['DOMPDF_ENABLE_AUTOLOAD'] ?? false);
        define('DOMPDF_ENABLE_REMOTE', $_ENV['DOMPDF_ENABLE_REMOTE'] ?? true);
        ini_set('max_execution_time', $_ENV['MAX_EXECUTION_TIME'] ?? 1200);
        ini_set('display_errors', $_ENV['DISPLAY_ERRORS'] ?? 1);

        $error_reporting = $_ENV['ERROR_REPORTING'] ?? 'E_ALL';

        if ($error_reporting == 'E_ERROR') {
            error_reporting(E_ERROR);
        }

        elseif ($error_reporting == 'E_WARNING') {
            error_reporting(E_ERROR | E_WARNING);
        }

        elseif ($error_reporting == 'E_PARSE') {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
        }

        elseif ($error_reporting == 'E_NOTICE') {
            error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
        }

        elseif ($error_reporting == 'E_ALL') {
            error_reporting(E_ALL);
        }

        if (isset($_ENV['APP_TIMEZONE'])) {
            date_default_timezone_set($_ENV['APP_TIMEZONE']);
        }

        if (isset($_ENV['APP_LANG'])) {

            $language = explode(';', $_ENV['APP_LANG']);

            switch (count($language)) {

                case 3:
                    setlocale(LC_TIME, $language[0], $language[1], $language[2]);
                    break;

                case 2:
                    setlocale(LC_TIME, $language[0], $language[1]);
                    break;

                default:
                    setlocale(LC_TIME, $language[0]);

            }

        }

        $this->setCostCenterId(5551);
        $this->setSubCostCenterId(5400);
        $this->setEmail($_ENV['DEFAULT_MAIL']);
        $this->setPageError($_ENV['PAGE_ERROR']);
        $this->setBaseHref($_ENV['BASE_HREF']);

        $items = explode(';', $_ENV['DOMAIN_LIST']);

        foreach ($items as $item) {
            $this->addDomainList($item);
        }

        if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv') {

            $conn = new Connector\SQLServer($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
            $this->addConnector(0, $conn);

        }

        $GLOBALS['CONFIG'] = $this;

        $this->createConstants();

    }

    public function createConstants(): void
    {

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

        define('LIMITE_BIGINT', 100000000000000);

    }

    public function addDomainList(string $domain)
    {
        $this->_domain_list[] = $domain;
    }

    public function getDomainList(): array
    {
        return $this->_domain_list;
    }

    public function getCostCenterId(): int
    {
        return $this->_cost_center_id;
    }

    public function setCostCenterId($cost_center_id)
    {
        $this->_cost_center_id = $cost_center_id;
    }

    public function getSubCostCenterId(): int
    {
        return $this->_sub_cost_center_id;
    }

    public function setSubCostCenterId($sub_cost_center_id)
    {
        $this->_sub_cost_center_id = $sub_cost_center_id;
    }

    public function getDigitalId(): int
    {
        return $this->_digital_id;
    }

    public function setDigitalId($digital_id)
    {
        $this->_digital_id = $digital_id;
    }

    public function getTitle(): string
    {
        return $this->_title;
    }

    public function setTitle($title)
    {
        $this->_title = $title;
    }

    public function getBaseHref(): string
    {
        return $this->_base_href;
    }

    public function setBaseHref($base_href)
    {
        $this->_base_href = $base_href;
    }

    public function getPageNotFound(): string
    {
        return $this->_page_not_found;
    }

    public function setPageNotFound($page_not_found)
    {
        $this->_page_not_found = $page_not_found;
    }

    public function setPageError(string $page_error): void
    {
        $this->_page_error = $page_error;
    }

    public function getPageError(): string
    {
        return $this->_page_error;
    }

    public function getEmail(): string
    {
        return $this->_email;
    }

    public function setEmail($email)
    {
        $this->_email = $email;
    }

    public function addConnector($id, Connector\Connector $connector)
    {
        $this->_connectors[$id] = $connector;
    }

    public function getConnectorById($id = 0): Connector\Connector
    {

        if (!isset($this->_connectors[$id]))
            Application::error("Connector <b>#$id</b> não existe!", 1);

        return $this->_connectors[$id];

    }

}
