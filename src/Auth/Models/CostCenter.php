<?php
namespace Fluxion\Auth\Models;

use Fluxion\Model;
use Fluxion\Util;

class CostCenter extends Model
{

    const PRESI = 5802;

    // NEGOCIOS DE VAREJO

    //const VINOV = 5815;

    //const DEESC = 5102;

    const SUAGR = 5400;
    const SRA = 5250;

    const GENAG = 5128;
    const GESAG = 5551;
    const GEGRO = 5128;
    const GEFOA = 5551;
    const GETAT = 5130;
    const GESIT = 5145;

    // NEGOCIOS DE ATACADO

    const VINAT = 5822;

    const DERAT = 5133;
    //const DEPVA = 5101;
    const DERED = 5106;

    const SUMEP = 5194;
    const SUPRA = 5795;
    const SUNCO = 5190;

    // REDE DE VAREJO

    const VIVAR = 5819;
    //const SR_PRIVATE = 6515;
    //const SR_ESP = 6501;

    const SURED = 5094;
    const SUESP = 5021;
    const SURVA = 5879;
    const SURVB = 5549;
    const SURVC = 5601;
    const CEMOB = 7017;

    // OUTROS

    //const CESNE = 7094;

    protected $_verbose_name = 'Unidade';

    protected $_field_id_ai = false;

    protected $_table = 'auth.cost_center';

    protected $_default_cache = true;

    protected $_order = [
        ['type', 'DESC'],
        ['id', 'ASC'],
    ];

    public function changeState($state): void
    {

        /*if ($edit == $this::STATE_EDIT) {
            unset($this->_fields['subordination']['foreign_key']);
        }*/

    }

    public $id = [
        'type' => 'integer',
        'search' => true,
        'min' => 1,
        'max' => 9999,
        'label' => '#',
        'size' => 2,
    ];

    public $type = [
        'type' => 'string',
        'search' => true,
        'label' => 'Tipo',
        'required' => true,
        'readonly' => true,
        'choices' => [
            'AG' => 'AG - Agência',
            'CL' => 'CL',
            'CN' => 'CN',
            'CO' => 'CO',
            'CR' => 'CR',
            'DG' => 'DG - Agência Digital/Aceleração',
            'DI' => 'DI - Diretoria',
            'ER' => 'ER',
            'GB' => 'GB',
            'GE' => 'GE',
            'GI' => 'GI',
            'GN' => 'GN',
            'MN' => 'MN',
            'OU' => 'OU',
            'PA' => 'PA - Posto de Atendimento',
            'PE' => 'PE',
            'PR' => 'PR',
            'RC' => 'RC',
            'RE' => 'RE',
            'RF' => 'RF',
            'SC' => 'SC',
            'SE' => 'SE',
            'SG' => 'SG',
            'SN' => 'SN - Superintendência Nacional',
            'SR' => 'SR - Superintendência Regional',
            'VP' => 'VP',
        ],
        'size' => 3,
    ];

    public $name = [
        'type' => 'string',
        'search' => true,
        'label' => 'Nome',
        'required' => true,
        'readonly' => true,
        'size' => 6,
    ];

    public $subordination = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        //'foreign_key_show' => false,
        'readonly' => true,
        'min' => 1,
        'max' => 9999,
        'label' => 'Vinculação',
        'size' => 6,
    ];

    public $subordination1 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        'foreign_key_show' => false,
        'readonly' => true,
        'foreign_key_filter' => [
            'type' => 'SR',
            'name__like' => 'SEV %',
        ],
        'min' => 1,
        'max' => 9999,
        'label' => 'SEV',
        'size' => 6,
    ];

    public $subordination0 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        'foreign_key_show' => false,
        'readonly' => true,
        'foreign_key_filter' => [
            'type' => 'SR',
        ],
        'min' => 1,
        'max' => 9999,
        'label' => 'SR',
        'size' => 6,
    ];

    public $subordination2 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        'foreign_key_show' => false,
        'readonly' => true,
        'foreign_key_filter' => [
            'type' => 'SN',
        ],
        'min' => 1,
        'max' => 9999,
        'label' => 'SN',
        'size' => 6,
    ];

    public $subordination4 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        'foreign_key_show' => false,
        'readonly' => true,
        'foreign_key_filter' => [
            'type' => 'DI',
        ],
        'min' => 1,
        'max' => 9999,
        'label' => 'DI',
        'size' => 6,
    ];

    public $subordination3 = [
        'type' => 'integer',
        'search' => true,
        'foreign_key' => __CLASS__,
        'foreign_key_fake' => true,
        'foreign_key_show' => false,
        'readonly' => true,
        'foreign_key_filter' => [
            'type' => 'VP',
        ],
        'min' => 1,
        'max' => 9999,
        'label' => 'VP',
        'size' => 6,
    ];

    public $city = [
        'type' => 'integer',
        'label' => 'Cidade',
        'foreign_key' => 'Esj\App\Rtc\Models\Municipio',
        'foreign_key_fake' => true,
        'readonly' => true,
        'required' => false,
        'size' => 6,
    ];

    public $scale = [
        'type' => 'integer',
        'label' => 'Porte',
        'readonly' => true,
        'choices' => [
            0 => 'Sem Porte',
            1 => 'Porte 1',
            2 => 'Porte 2',
            3 => 'Porte 3',
            4 => 'Porte 4',
            5 => 'Porte 5',
        ],
        'size' => 6,
    ];

    public $initials = [
        'type' => 'string',
        'label' => 'Sigla',
        'required' => false,
        'readonly' => true,
        'search' => true,
        'size' => 3,
    ];

    public $email = [
        'type' => 'string',
        'label' => 'E-Mail',
        'required' => false,
        'size' => 9,
    ];

    public $cnpj = [
        'type' => 'integer',
        'label' => 'CNPJ',
        'required' => false,
        'size' => 6,
    ];

    public $zip_code = [
        'type' => 'string',
        'label' => 'CEP',
        'required' => false,
        'readonly' => true,
        'size' => 6,
    ];

    public $address = [
        'type' => 'string',
        'label' => 'Endereço',
        'required' => false,
        'readonly' => true,
        'size' => 12,
    ];

    public $address2 = [
        'type' => 'string',
        'label' => 'Complemento',
        'required' => false,
        'readonly' => true,
        'size' => 12,
    ];

    public $credit_enabled = [
        'type' => 'boolean',
        'label' => 'Habilitada para Crédito',
        'filter' => true,
    ];

    protected $_indexes = [
        ['subordination'],
        ['type'],
    ];

    public function tags(): array
    {

        $tags = [];

        if ($this->credit_enabled) {

            $tags[] = ['color' => 'blue', 'label' => 'Habilitada'];

        }

        return $tags;

    }

    public function __toString()
    {

        if (!$this->id) {
            return "[TODAS AS UNIDADES]";
        }

        return sprintf("%04d", $this->id) . ' - ' . $this->type . ' ' . $this->name;

    }

    public function numero(): string
    {
        return sprintf("%04d", $this->id);
    }

    public function nomeUnidade(): string
    {

        return $this->type . ' ' . $this->name;

    }

    public function sigla(): string
    {

        if ($this->id == self::PRESI) {
            return 'CAIXA';
        }

        if (in_array($this->type, ['PA', 'AG', 'SR'])) {
            return $this->type . sprintf("%04d", $this->id);
        }

        return (string) $this->initials;

    }

    public function subtitle(): string
    {

        $config = $this->_config;
        $auth = $this->_auth;

        $arr = [];

        if (!isset($GLOBALS['cost_centers'])) {
            $GLOBALS['cost_centers'] = [];
        }

        if (!is_null($this->subordination)) {

            if (!isset($GLOBALS['cost_centers'][$this->subordination])) {
                $GLOBALS['cost_centers'][$this->subordination] = (string) CostCenter::loadById($this->subordination, $config, $auth);
            }

            $arr[] = 'Vinculação: ' . $GLOBALS['cost_centers'][$this->subordination];

        }

        return implode(' | ', $arr);

    }

    public function email(): string
    {

        return "$this->email";

    }

    public function updateInfo(): ?string
    {
        return Util::jsDate($this->_insert);
    }

    public function updateTitle(): string
    {
        return 'Incluída em';
    }

    public function chartLabel(): string
    {
        return $this->initials ?? $this->numero();
    }

}
