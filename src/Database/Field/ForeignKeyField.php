<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\{Database\Field, Exception, Model};

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKeyField extends Field
{

    private ?Field $_field = null;

    protected string $_name;

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function setModel(Model $model): void
    {
        $this->_model = $model;
    }

    public function getField(): Field
    {
        return $this->_field;
    }

    /** @throws Exception */
    public function __construct(public string  $class_name,
                                public bool    $real = false,
                                public bool    $show = false,
                                public ?string $type = null,
                                public ?array  $filter = null,
                                public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?string $column_name = null)
    {

        $class = new $class_name;

        if (!$class instanceof Model) {
            throw new Exception(message: "Classe '$class_name' não é Model", log: false);
        }

        $this->_reference_model = $class;

        $this->_field = $class->getFieldId();

        parent::__construct();

    }

    /** @throws Exception */
    public function initialize(): void
    {

        $field = $this->_model->getFields()[$this->_name];

        $this->_type = $this->_field->getType();
        $this->_type_target = $this->_field->getTypeTarget();
        $this->_type_property = $this->_field->_type_property;

        if ($field->getType() == 'string') {
            $this->max_length = $this->_field->max_length;
        }

        parent::initialize();

    }

    public function translate(mixed $value): string|int|null|float
    {

        return match ($this->_type) {
            'integer' => (new IntegerField())->translate($value),
            'float' => (new FloatField())->translate($value),
            'date' => (new DateField())->translate($value),
            'datetime' => (new DateTimeField())->translate($value),
            default => (new StringField())->translate($value),
        };

    }

    /**
     * @throws Exception
     */
    public function validate(mixed &$value): bool
    {

        return match ($this->_type) {
            'integer' => (new IntegerField())->validate($value),
            'float' => (new FloatField())->validate($value),
            'date' => (new DateField())->validate($value),
            'datetime' => (new DateTimeField())->validate($value),
            default => (new StringField())->validate($value),
        };

    }

    public function isForeignKey(): bool
    {
        return true;
    }

}
