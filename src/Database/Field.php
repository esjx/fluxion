<?php
namespace Fluxion\Database;

use Fluxion\Model2;
use Fluxion\Mask\Mask;
use Fluxion\CustomException;

abstract class Field
{

    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_PASSWORD = 'password';
    const TYPE_INTEGER = 'integer';
    const TYPE_COLOR = 'color';
    const TYPE_FLOAT = 'float';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';

    protected mixed $_value = null;
    protected mixed $_saved_value = null;
    protected string $_name;
    public ?string $column_name = null;
    protected Model2 $_model;
    protected string $_type = self::TYPE_STRING;
    protected string $_type_target = 'string';
    protected string $_type_property;

    public ?string $label = null;
    public ?string $mask = null;
    public ?string $placeholder = null;
    public ?string $pattern = null;
    public ?string $mask_class = null;
    public ?bool $required = false;
    public ?bool $protected = false;
    protected bool $_changed = false;
    protected bool $_loaded = false;
    public ?bool $readonly = false;
    public ?bool $primary_key = false;
    public ?bool $identity = false;
    public ?bool $fake = false;
    public ?int $max_length = null;
    public ?array $choices = null;
    public ?array $choices_colors = null;
    public ?int $size = 12;
    public ?int $decimal_places = 2;
    public mixed $default = null;
    public bool $default_literal = false;
    public null|int|string $min_value = null;
    public null|int|string $max_value = null;
    public ?array $filter = null;

    public ?ForeignKey $foreign_key = null;
    public ?ManyToMany $many_to_many = null;

    public function isChanged(): bool
    {
        return $this->_changed;
    }

    public function isLoaded(): bool
    {
        return $this->_loaded;

    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function getType(): string
    {
        return $this->_type;
    }

    public function setTypeProperty(string $type_property): void
    {
        $this->_type_property = $type_property;
    }

    public function setModel(Model2 $model): void
    {
        $this->_model = $model;
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && empty($value)) {
            return false;
        }

        return true;

    }

    public function format(mixed $value): mixed
    {
        return $value;
    }

    public function update(): void
    {

    }

    public function getValue($row = false): mixed
    {

        $this->update();

        if ($row) {
            return $this->_value;
        }

        if (!is_null($this->many_to_many) && is_null($this->_value) && $this->_loaded) {

            #TODO: buscar dados de tabelas MN

        }

        return $this->format($this->_value);

    }

    public function getSavedValue($row = false): mixed
    {

        if ($row) {
            return $this->_saved_value;
        }

        return $this->format($this->_saved_value);

    }

    public function translate(mixed $value): mixed
    {
        return (string) $value;
    }

    /** @throws CustomException */
    public function setValue(mixed $value, bool $database = false): void
    {

        if (!$database && !$this->validate($value)) {
            throw new CustomException(message: "Valor '$value' inválido para o campo '$this->_name'", log: false);
        }

        $new_value = $this->translate($value);

        if ($this->_value != $new_value) {
            $this->_changed = true;
            $this->_value = $new_value;
        }

        if ($database) {
            $this->_loaded = true;
            $this->_changed = false;
            $this->_saved_value = $new_value;
        }

    }

    public function __construct()
    {

    }

    /** @throws CustomException */
    public function initialize(): void
    {

        $class = get_class($this->_model);

        if (!in_array($this->size, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            throw new CustomException(message: "Tamanho do campo '$class:$this->_name' inválido: '$this->size'", log: false);
        }

        if (is_null($this->many_to_many) && $this->_type_property != 'mixed' && !str_contains($this->_type_property, $this->_type_target)) {
            throw new CustomException(message: "Tipo do campo '$class:$this->_name' inválido: '$this->_type_property'", log: false);
        }

        if (!is_null($this->many_to_many) && $this->_type_property != 'mixed' && !str_contains($this->_type_property, 'array')) {
            throw new CustomException(message: "Tipo do campo '$class:$this->_name' inválido: '$this->_type_property'", log: false);
        }

        if (!$this->required && $this->_type_property != 'mixed' && !str_contains($this->_type_property, '?')) {
            throw new CustomException(message: "Campo '$class:$this->_name' deve permitir nulos", log: false);
        }

        if (empty($this->label)) {
            $this->label = ucfirst($this->_name);
        }

        if (!is_null($this->mask_class)) {

            if (!class_exists($this->mask_class)) {
                throw new CustomException(message: "Mascára '$class:$this->mask_class' não encontrada", log: false);
            }

            $mask = new $this->mask_class;

            if (!is_subclass_of($mask, Mask::class)) {
                throw new CustomException(message: "Classe '$this->mask_class' não herda 'Mask'", log: false);
            }

            $this->mask = $mask->mask;
            $this->placeholder = $mask->placeholder;
            $this->pattern = $mask->pattern_validator;
            $this->label = $this->label ?? $mask->label;
            $this->max_length = $this->max_length ?? $mask->max_length;

        }

        if (is_null($this->max_length) && in_array($this->_type, [self::TYPE_STRING, self::TYPE_PASSWORD])) {
            $this->max_length = 255;
        }

        if (is_null($this->column_name)) {
            $this->column_name = $this->_name;
        }

        $this->foreign_key?->initialize();
        $this->many_to_many?->initialize();

    }

}
