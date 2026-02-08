<?php
namespace Fluxion\Database;

use Fluxion\Exception;
use Fluxion\Model;

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
    protected Model $_model;

    protected ?Model $_reference_model = null;

    protected string $_type = self::TYPE_STRING;
    protected string $_type_target = 'string';
    protected string $_type_property;

    public bool $inverted = false;
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
    public ?int $decimal_places = 2;
    public mixed $default = null;
    public bool $default_literal = false;
    public null|int|string $min_value = null;
    public null|int|string $max_value = null;
    public ?array $filter = null;

    public function isChanged(): bool
    {
        return $this->_changed;
    }

    public function hasDefaultValue(): bool
    {
        return !is_null($this->default);

    }

    public function isPrimaryKey(): bool
    {
        return $this->primary_key;
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

    public function setModel(Model $model): void
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

    public function getReferenceModel(): Model
    {
        return $this->_reference_model;
    }

    public function update(): void
    {

    }

    public function getValue($row = false): mixed
    {

        if ($row) {
            $this->update();
            return $this->_value;
        }

        if (empty($this->_value)) return null;

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

    /** @throws Exception */
    public function setValue(mixed $value, bool $database = false): void
    {

        if (!$database && !$this->validate($value)) {

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            throw new Exception(message: "Valor '$value' inválido para o campo '$this->_name'", log: false);

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

    /** @throws Exception */
    public function initialize(): void
    {

        $class = get_class($this->_model);

        if ($this->_type_property != 'mixed' && !str_contains($this->_type_property, '?')) {
            throw new Exception(message: "Campo '$class:$this->_name' deve permitir nulos", log: false);
        }

        if (is_null($this->max_length) && in_array($this->_type, [self::TYPE_STRING, self::TYPE_PASSWORD])) {
            $this->max_length = 255;
        }

        if (is_null($this->column_name)) {
            $this->column_name = $this->_name;
        }

    }

    public function isForeignKey(): bool
    {
        return false;
    }

    public function isManyToMany(): bool
    {
        return false;
    }

    public function getTypeTarget(): string
    {
        return $this->_type_target;
    }

}
