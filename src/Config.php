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

        if (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] == 'sqlsrv2') {

            $conn = new Connector\SQLServer($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
            $this->addConnector(0, $conn);

        }

        $GLOBALS['CONFIG'] = $this;

        $this->createConstants();

    }

    public function createConstants(): void
    {

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
