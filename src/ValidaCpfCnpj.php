<?php
namespace Esj\Core;

class ValidaCpfCnpj
{

    const PF = 1;
    const PJ = 2;

    protected ?string $valor = null;

    function getValue(): ?string
    {
        return $this->valor;

    }

    function __construct($valor = null, $tipo = 0)
    {

        $this->valor = (string) preg_replace('/[^0-9]/', '', $valor);

        if ($tipo == self::PF) {

            $this->valor = sprintf("%011d", $this->valor * 1);

        } elseif ($tipo == self::PJ) {

            $this->valor = sprintf("%014d", $this->valor * 1);

        }

    }

    protected function verificaCpfCnpj(): ?string
    {

        if (strlen($this->valor) == 11) {

            return 'CPF';

        } elseif (strlen($this->valor) == 14) {

            return 'CNPJ';

        } else {

            return null;

        }

    }

    public function getType(): ?int
    {

        if (strlen($this->valor) == 11) {

            return self::PF;

        } elseif (strlen($this->valor) == 14) {

            return self::PJ;

        } else {

            return null;

        }

    }

    protected function verificaIgualdade(): bool
    {

        $caracteres = str_split($this->valor);

        foreach ($caracteres as $digito) {

            if ($caracteres[0] != $digito) {

                return false;

            }

        }

        return true;

    }

    public static function calcDigitosPosicoes($digitos, $posicoes = 10, $soma_digitos = 0): string
    {

        for ($i = 0; $i < strlen($digitos); $i++) {

            $soma_digitos += $digitos[$i] * $posicoes;

            $posicoes--;

            if ($posicoes < 2) {

                $posicoes = 9;

            }

        }

        $soma_digitos = $soma_digitos % 11;

        if ($soma_digitos < 2) {

            $soma_digitos = 0;

        } else {

            $soma_digitos = 11 - $soma_digitos;

        }

        return $digitos . $soma_digitos;

    }

    protected function validaCpf(): bool
    {

        $this->valor = sprintf("%011d", $this->valor * 1);

        $digitos = substr($this->valor, 0, 9);

        $novo = $this->calcDigitosPosicoes($digitos);

        $novo = $this->calcDigitosPosicoes($novo, 11);

        $saida = (!$this->verificaIgualdade() && $novo == $this->valor);

        if ($saida) {
            $this->valor = sprintf("%011d", $this->valor * 1);
        }

        return $saida;

    }

    protected function validaCnpj(): bool
    {

        $this->valor = sprintf("%014d", $this->valor * 1);

        $digitos = substr($this->valor, 0, 12);

        $novo = $this->calcDigitosPosicoes($digitos, 5);

        $novo = $this->calcDigitosPosicoes($novo, 6);

        $saida = (!$this->verificaIgualdade() && $novo == $this->valor);

        if ($saida) {
            $this->valor = sprintf("%014d", $this->valor * 1);
        }

        return $saida;

    }

    public function valida(): bool
    {

        if ($this->verificaCpfCnpj() == 'CPF') {

            return $this->validaCpf();

        } elseif ($this->verificaCpfCnpj() == 'CNPJ') {

            return $this->validaCnpj();

        } else {

            return false;

        }

    }

    public function valida2(): bool
    {

        if (strlen($this->valor) > 11 || !$this->validaCpf()) {

            return $this->validaCnpj();

        }

        return true;

    }

    public function __toString(): string
    {
        return $this->formata();
    }

    public function format(): string
    {
        return $this->formata();
    }

    public function formata(): string
    {

        $formatado = '';

        if ($this->verificaCpfCnpj() == 'CPF') {

            if ($this->validaCpf()) {

                $formatado = substr($this->valor, 0, 3) . '.';
                $formatado .= substr($this->valor, 3, 3) . '.';
                $formatado .= substr($this->valor, 6, 3) . '-';
                $formatado .= substr($this->valor, 9, 2);

            } else {

                $formatado .= 'CPF INVÁLIDO';

            }

        } elseif ($this->verificaCpfCnpj() == 'CNPJ') {

            if ($this->validaCnpj()) {

                $formatado = substr($this->valor, 0, 2) . '.';
                $formatado .= substr($this->valor, 2, 3) . '.';
                $formatado .= substr($this->valor, 5, 3) . '/';
                $formatado .= substr($this->valor, 8, 4) . '-';
                $formatado .= substr($this->valor, 12, 2);

            } else {

                $formatado .= 'CNPJ INVÁLIDO';

            }

        }

        return $formatado;

    }

    public static function formatar(int $numero, ?int $tipo = self::PJ): ?string
    {

        $validador = new self($numero, $tipo);

        if (!$validador->valida() && $tipo == self::PJ) {

            $validador = new self($numero, self::PF);

        }

        if (!$validador->valida()) {

            return '#INVÁLIDO#';

        }

        return $validador->format();

    }

}
