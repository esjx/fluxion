<?php
namespace Fluxion;

class Extra
{

    const DESCRICAO_APROVADA = '<span style="color: blue;"><b>APROVADA</b></span>';
    const DESCRICAO_REPROVADA = '<span style="color: red;"><b>REPROVADA</b></span>';
    const DESCRICAO_INSUFICIENTE = '<span style="color: orange;"><b>INSUF.</b></span>';
    const DESCRICAO_OK = '<span style="color: blue;"><b>OK</b></span>';
    const DESCRICAO_EXCECAO = '<span style="color: orange;"><b>EXCEÇÃO</b></span>';
    const DESCRICAO_CUSTOMIZADA = '<span style="color: green;"><b>CUSTOMIZADA</b></span>';
    const DESCRICAO_DISPENSADO = '<span style="color: green;"><b>DISPENSADO</b></span>';

    protected $_config;
    protected $_auth;

    protected $_criticas = [];

    protected $_memoria = '';

    protected $_versao = '';

    public function __construct(Config $config = null, AuthOld $auth = null)
    {

        if (is_null($config)) {
            $config = $GLOBALS['CONFIG'];
        }

        if (is_null($auth)) {
            $auth = $GLOBALS['AUTH'];
        }

        $this->_config = $config;
        $this->_auth = $auth;

    }

    public function criticas(): array
    {

        return $this->_criticas;

    }

    public function memoria(): string
    {

        return $this->_memoria;

    }

    protected function formatar($valor, $casas = 2, $sufixo = '', $prefixo = ''): string
    {

        if ($casas == -1) {

            $casas = ($valor == intval($valor)) ? 0 : 2;

        }

        $ret = number_format($valor, $casas, ',', '.');

        if ($sufixo != '') {
            $ret .= ' ' . $sufixo;
        }

        if ($prefixo != '') {
            $ret = $prefixo . ' ' . $ret;
        }

        if ($valor == 0 || $valor == 'Não') {
            $ret = $this->color($ret);
        }

        return $ret;

    }

    protected function color(string $text): string
    {
        return '<span style="color: #999999;">' . $text . '</span>';
    }

}
