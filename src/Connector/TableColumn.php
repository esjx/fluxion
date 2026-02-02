<?php
namespace Fluxion\Connector;

class TableColumn
{

    public string $name;
    public int $id;
    public string $type;
    public bool $nullable;
    public bool $required;
    public bool $identity;
    public ?int $max_length = null;
    public ?int $precision = null;
    public ?int $scale = null;
    public bool $extra = true;
    public ?string $default_value = null;
    public ?string $default_constraint = null;

    public function __toString(): string
    {

        # Criação do comando

        $command = "$this->type";

        # Complementos

        if ($this->type == 'varchar' && $this->max_length) {
            $command .= "($this->max_length)";
        }

        elseif ($this->type == 'varchar' && !$this->max_length) {
            $command .= "(max)";
        }

        elseif ($this->type == 'nvarchar') {
            $command .= "($this->max_length)";
        }

        elseif ($this->type == 'numeric') {
            $command .= "($this->precision,$this->scale)";
        }

        # Valores nulos

        if ($this->required || $this->identity) {
            $command .= ' not null';
        }

        # Auto incremento

        if ($this->identity) {
            $command .= ' identity(1,1)';
        }

        # Valor padrão

        if (!is_null($this->default_value)) {
            $command .= " default $this->default_value";
        }

        return $command;

    }

}
