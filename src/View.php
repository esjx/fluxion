<?php
namespace Esj\Core;

use DateTime;
use Exception;
use IntlDateFormatter;

class View
{

    private $view;
    private $vars;

    function __construct($view = null, $vars = null)
    {
        
        if($view != null)
            $this->setView($view);
        
        $this->vars = $vars;

    }

    function __get($var)
    {
        if (isset($this->vars[$var]))
            return $this->vars[$var];
        return null;
    }

    function __set($var, $value)
    {
        $this->vars[$var] = $value;
    }

    public function setView($view)
    {

		$file_exits = false;
		
        if (file_exists($view)) {
            $file_exits = true;
            $this->view = $view;
        }

        if (!$file_exits)
            Application::error("Arquivo <b>$view</b> não existe!", 501);
        
    }

    public function getView()
    {
        return $this->view;
    }

    public function load()
    {
        ob_start();
        if(isset($this->view) && file_exists($this->view))
            require $this->view;
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }

    public function show($view = null)
    {
        if($view != null)
            $this->setView($view);
        echo $this->load();
    }

    public static function formatNumber(float $value, bool $minimizar = true, int $decimals = 2): string
    {

        $sufix = '';

        $decimal_separator = ',';
        $thousands_separator = '.';

        $gatilho = 900;

        if ($minimizar) {

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'mil';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'milhões';
            }

            if ($value > $gatilho) {
                $value /= 1000;
                $sufix = 'bilhões';
            }

        }

        return trim(number_format($value, $decimals, $decimal_separator, $thousands_separator) . ' ' . $sufix);

    }

    public static function dataExtenso(string $data, $full = false): ?string
    {

        try {

            $data = new DateTime($data);

            $type = ($full) ? IntlDateFormatter::FULL : IntlDateFormatter::LONG;

            $formatter = new IntlDateFormatter('pt_BR',
                $type,
                IntlDateFormatter::NONE,
                'America/Sao_Paulo',
                IntlDateFormatter::GREGORIAN);

            return $formatter->format($data);

        } catch (Exception $e) {

            return null;

        }

    }

    public static function formatCpfCnpj(int $numero, ?int $tipo = ValidaCpfCnpj::PJ): ?string
    {

        $validador = new ValidaCpfCnpj($numero, $tipo);

        if (!$validador->valida() && $tipo == ValidaCpfCnpj::PJ) {

            $validador = new ValidaCpfCnpj($numero, ValidaCpfCnpj::PF);

        }

        if (!$validador->valida()) {

            return '#INVÁLIDO#';

        }

        return $validador->format();

    }

    public static function formatTempo(?int $minutos): ?string
    {

        if (is_null($minutos)) {
            return '--:--';
        }

        $horas = floor($minutos / 60);
        $minutos = $minutos % 60;

        return sprintf("%02d:%02d", $horas, $minutos);

    }

    public static function proximoDiaUtil(?string $date): string
    {
        return Util::proximoDiaUtil($date);
    }

    public static function formatDate(?string $date, string $format = 'd/m/Y H:i:s'): ?string
    {

        if (is_null($date)) {
            return null;
        }

        try {

            return (new DateTime($date))->format($format);

        } catch (Exception $e) {

            return null;

        }

    }

    public static function tipoPessoa($tipo): string
    {
        return ($tipo == 1) ? 'CPF' : 'CNPJ';
    }

    public static function xOuEspaco($condicao): string
    {
        return ($condicao) ? '<b>X</b>' : '&nbsp;&nbsp;';
    }

    public static function valorPorExtenso( $valor = 0, $bolExibirMoeda = true, $bolPalavraFeminina = false ): string
    {

        //$valor = self::removerFormatacaoNumero( $valor );

        $singular = null;
        $plural = null;

        if ( $bolExibirMoeda )
        {
            $singular = array("centavo", "real", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
            $plural = array("centavos", "reais", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
        }
        else
        {
            $singular = array("", "", "mil", "milhão", "bilhão", "trilhão", "quatrilhão");
            $plural = array("", "", "mil", "milhões", "bilhões", "trilhões","quatrilhões");
        }

        $c = array("", "cem", "duzentos", "trezentos", "quatrocentos","quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos");
        $d = array("", "dez", "vinte", "trinta", "quarenta", "cinquenta","sessenta", "setenta", "oitenta", "noventa");
        $d10 = array("dez", "onze", "doze", "treze", "quatorze", "quinze","dezesseis", "dezessete", "dezoito", "dezenove");
        $u = array("", "um", "dois", "três", "quatro", "cinco", "seis","sete", "oito", "nove");


        if ( $bolPalavraFeminina )
        {

            if ($valor == 1)
            {
                $u = array("", "uma", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
            }
            else
            {
                $u = array("", "um", "duas", "três", "quatro", "cinco", "seis","sete", "oito", "nove");
            }

            $c = array("", "cem", "duzentas", "trezentas", "quatrocentas","quinhentas", "seiscentas", "setecentas", "oitocentas", "novecentas");

        }

        $z = 0;

        $valor = number_format( $valor, 2, ".", "." );
        $inteiro = explode( ".", $valor );

        for ( $i = 0; $i < count( $inteiro ); $i++ )
        {
            for ( $ii = mb_strlen( $inteiro[$i] ); $ii < 3; $ii++ )
            {
                $inteiro[$i] = "0" . $inteiro[$i];
            }
        }

        // $fim identifica onde que deve se dar junção de centenas por "e" ou por "," ;)
        $rt = null;
        $fim = count( $inteiro ) - ($inteiro[count( $inteiro ) - 1] > 0 ? 1 : 2);
        for ( $i = 0; $i < count( $inteiro ); $i++ )
        {
            $valor = $inteiro[$i];
            $rc = (($valor > 100) && ($valor < 200)) ? "cento" : $c[$valor[0]];
            $rd = ($valor[1] < 2) ? "" : $d[$valor[1]];
            $ru = ($valor > 0) ? (($valor[1] == 1) ? $d10[$valor[2]] : $u[$valor[2]]) : "";

            $r = $rc . (($rc && ($rd || $ru)) ? " e " : "") . $rd . (($rd && $ru) ? " e " : "") . $ru;
            $t = count( $inteiro ) - 1 - $i;
            $r .= $r ? " " . ($valor > 1 ? $plural[$t] : $singular[$t]) : "";
            if ( $valor == "000")
                $z++;
            elseif ( $z > 0 )
                $z--;

            if ( ($t == 1) && ($z > 0) && ($inteiro[0] > 0) )
                $r .= ( ($z > 1) ? " de " : "") . $plural[$t];

            if ( $r )
                $rt = $rt . ((($i > 0) && ($i <= $fim) && ($inteiro[0] > 0) && ($z < 1)) ? ( ($i < $fim) ? " e " : " e ") : " ") . $r;
        }

        $rt = mb_substr( $rt, 1 );

        return mb_strtoupper($rt ? trim( $rt ) : "zero reais", 'utf8');

    }

    /*
     * NOVOS
     * */

    public function number($value): string
    {
        return number_format($value ?? 0, 0, ',', '.');
    }

    public function decimal($value): string
    {
        return number_format($value ?? 0, 2, ',', '.');
    }

    public function date($date): ?string
    {
        return self::formatDate($date, 'd/m/Y');
    }

    public function fullDate($date): string
    {
        return self::dataExtenso($date, true);
    }

    public function dateTime($datetime): string
    {
        return self::formatDate($datetime);
    }

    public function time($minutes): string
    {
        return self::formatTempo($minutes);
    }

    public static function nextWorkDay(?string $date): string
    {
        return Util::proximoDiaUtil($date);
    }

    public static function extenso($valor = 0, $bolExibirMoeda = true, $bolPalavraFeminina = false): string
    {
        return self::valorPorExtenso($valor, $bolExibirMoeda, $bolPalavraFeminina);
    }

    public static function cpfCnpj(int $numero, ?int $tipo = ValidaCpfCnpj::PJ): string
    {
        return self::formatCpfCnpj($numero, $tipo);
    }

}
