<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\{Model2, CustomException};

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKeyField extends Field
{

    private ?Field $_field = null;

    protected string $_name;

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function setModel(Model2 $model): void
    {
        $this->_model = $model;
    }

    public function getField(): Field
    {
        return $this->_field;
    }

    /** @throws CustomException */
    public function __construct(public string  $class_name,
                                public bool    $real = false,
                                public bool    $show = false,
                                public ?string $type = null,
                                public ?array  $filter = null,
                                public ?string $label = null,
                                public ?string $mask_class = null,
                                public ?bool   $required = false,
                                public ?bool   $primary_key = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?string $column_name = null,
                                public ?int    $size = 12)
    {

        $class = new $class_name;

        if (!$class instanceof Model2) {
            throw new CustomException(message: "Classe '$class_name' não é Model", log: false);
        }

        $this->_reference_model = $class;

        $this->_field = $class->getFieldId();

        parent::__construct();

    }

    /** @throws CustomException */
    public function initialize(): void
    {

        $field = $this->_model->getFields()[$this->_name];

        $this->_type = $this->_field->getType();
        $this->_type_target = $this->_field->getTypeTarget();

        if ($field->getType() == 'string') {
            $this->max_length = $this->_field->max_length;
        }

        parent::initialize();

    }

    public function translate(mixed $value): string|int|null|float
    {

        return match ($this->_type) {
            'int' => (new IntegerField())->translate($value),
            'float' => (new FloatField())->translate($value),
            'date' => (new DateField())->translate($value),
            'datetime' => (new DateTimeField())->translate($value),
            default => (new StringField())->translate($value),
        };

    }

    /**
     * @throws CustomException
     */
    public function validate(mixed &$value): bool
    {

        return match ($this->_type) {
            'int' => (new IntegerField())->validate($value),
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
