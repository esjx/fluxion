<?php
namespace Fluxion\Database;

use Fluxion\{FluxionException, Model};
use Fluxion\Query\{QuerySql, QueryWhere};

abstract class Field
{

    #TODO: Passar para um Enum

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
    const TYPE_GEOGRAPHY = 'geography';

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
    public ?bool $enabled = true;
    public ?bool $multiple = false;
    protected bool $_changed = false;
    protected bool $_loaded = false;
    public ?bool $readonly = false;
    public ?bool $primary_key = false;
    public ?bool $identity = false;
    public ?bool $fake = false;
    protected ?bool $_needs_audit = true;
    public ?bool $null_if_invalid = false;
    public ?bool $assistant_table = false;
    public ?int $max_length = null;
    public ?array $choices = null;
    public ?array $choices_colors = null;
    public ?int $precision = 18;
    public ?int $scale = 2;
    public bool $radio = false;
    public mixed $default = null;
    public bool $default_literal = false;
    public null|int|string $min_value = null;
    public null|int|string $max_value = null;
    public ?string $pattern = null;
    public ?string $validator_type = null;
    public ?string $text_transform = null;

    /** @var QueryWhere[] */
    public array $filters = [];
    public ?string $typeahead = null;

    public function isChanged(): bool
    {
        return $this->_changed;
    }

    public function needsAudit(): bool
    {
        return ($this->_needs_audit && !$this->fake);
    }

    public function hasDefaultValue(): bool
    {
        return !is_null($this->default);

    }

    public function isPrimaryKey(): bool
    {
        return $this->primary_key;
    }

    public function isIdentity(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return $this->_name;
    }

    public function getModel(): Model
    {
        return $this->_model;
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

    public function getTypeProperty(): string
    {
        return $this->_type_property;
    }

    public function setModel(Model $model): void
    {
        $this->_model = $model;
    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && is_null($value) && is_null($this->default)) {
            return false;
        }

        return true;

    }

    public function format(mixed $value): mixed
    {
        return $value;
    }

    public ?string $class_name = null;

    /**
     * @throws FluxionException
     */
    public function getReferenceModel(): Model
    {

        if (is_null($this->_reference_model)) {

            $class_name = $this->class_name;

            if ($class_name == get_class($this->_model)) {
                $class = $this->_model;
            }

            else {

                $class = new $class_name;

                if (!$class instanceof Model) {
                    throw new FluxionException(message: "Classe '$class_name' não é Model");
                }

            }

            $this->_reference_model = $class;

            $this->_type = $class->getFieldId()->getType();

        }

        return $this->_reference_model;

    }

    public function update(): void
    {

    }

    public function load(): void
    {

    }

    public function getValue($row = false): mixed
    {

        if ($row) {
            $this->update();
            return $this->_value;
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

    public function getAuditValue(mixed $value): string
    {

        if (is_null($value) || $value == []) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return (string) $value;

    }

    public function getExportValue(mixed $value): string
    {

        if (is_null($value) || $value == []) {
            return '';
        }

        return (string) $value;

    }

    public function translate(mixed $value): mixed
    {

        if (is_null($value) || $value === '') {
            return null;
        }

        return (string) $value;

    }

    public function clear(): void
    {
        $this->_value = null;
        $this->_saved_value = null;
        $this->_changed = false;
    }

    /** @throws FluxionException */
    public function setValue(mixed $value, bool $database = false): void
    {

        if (!$database && !$this->validate($value)) {

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            throw new FluxionException(message: "Valor '$value' inválido para o campo '$this->_name'");

        }

        $new_value = $this->translate($value);

        $this->load();

        if ($this->_type_target == 'array') {

            if (is_null($new_value)) {
                $new_value = [];
            }

            if (is_null($this->_value)) {
                $this->_value = [];
            }

            sort($new_value);
            sort($this->_value);

            if ($this->_value != $new_value) {
                $this->_changed = ($new_value != $this->_saved_value);
                $this->_value = $new_value;
            }

        }

        else {

            if ($this->_value !== $new_value) {
                $this->_changed = ($new_value !== $this->_saved_value);
                $this->_value = $new_value;
            }

        }

        if ($database) {
            $this->_loaded = true;
            $this->_changed = false;
            $this->_saved_value = $new_value;
        }

    }

    public function __construct()
    {

        if ($this->primary_key) {
            $this->required = true;
            $this->readonly = true;
        }

    }

    /** @throws FluxionException */
    public function initialize(): void
    {

        $class = get_class($this->_model);

        if ($this->_type_property != 'mixed'
            && !str_contains($this->_type_property, '?')
            && !str_contains($this->_type_property, 'null')) {
            throw new FluxionException(message: "Campo '$class:$this->_name' deve permitir nulos");
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

    public function isManyChoices(): bool
    {
        return false;
    }

    public function getTypeTarget(): string
    {
        return $this->_type_target;
    }

    public function getSearch(string $value): ?QueryWhere
    {
        return null;
    }

    /**
     * @throws FluxionException
     */
    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $detail = $this->_model->getDetail($this->getName());

        $enabled = $this->enabled;

        if (($this->readonly || $this->primary_key) && $this->_model->isSaved() && !is_null($this->_saved_value)) {
            $enabled = false;
        }

        return new FormField(
            name: $this->_name,
            label: $detail->label,
            label_conditions: $detail->label_conditions,
            visible: empty($detail->visible_conditions),
            visible_conditions: $detail->visible_conditions,
            enabled: $enabled && empty($detail->enabled_conditions),
            enabled_conditions: $detail->enabled_conditions,
            type: $this->_type,
            size: $detail->size,
            min: $this->min_value,
            max: $this->max_value,
            required: $this->required && empty($detail->required_conditions),
            required_conditions: $detail->required_conditions,
            placeholder: $detail->placeholder,
            pattern: $detail->pattern ?? $this->pattern,
            validator_type: $this->validator_type,
            text_transform: $this->text_transform,
            mask: $detail->mask,
            mask_literal: $detail->mask_literal,
            maxlength: $detail->max_length ?? $this->max_length,
            readonly: $this->readonly,
            group_name: $this->_model->getFormGroup($this->getName())?->label,
            help: $detail->help,
            help_conditions: $detail->help_conditions,
            choices_conditions: $detail->choices_conditions,
            zip_code: $detail->zip_code,
            value: $this->getValue(),
        );

    }

}
