<?php
namespace Fluxion\Database;

use Fluxion\CustomException;

abstract class Field
{

    const TYPE_STRING = 'string';
    const TYPE_PASSWORD = 'password';
    const TYPE_INTEGER = 'integer';
    const TYPE_COLOR = 'color';
    const TYPE_FLOAT = 'float';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';

    protected mixed $_value = null;
    protected string $_name;
    protected string $_type = self::TYPE_STRING;

    public ?string $label = null;
    public ?string $mask = null;
    public ?bool $required = false;
    public ?bool $protected = false;
    public ?bool $readonly = false;
    public ?int $max_length = 255;
    public ?array $choices = null;
    public ?array $choices_colors = null;
    public ?int $size = 12;
    public null|int|string $min_value = null;
    public null|int|string $max_value = null;
    public ?array $filter = null;

    function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && empty($value)) {
            return false;
        }

        return true;

    }

    public function getValue(): mixed
    {
        return $this->_value;
    }

    /** @throws CustomException */
    public function setValue(mixed $value): void
    {

        if (!$this->validate($value)) {
            throw new CustomException(message: "Valor $value inválido para o campo $this->label", log: false);
        }

        $this->_value = $value;

    }

}
