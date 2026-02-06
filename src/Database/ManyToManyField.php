<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\CustomException;
use Fluxion\MnModel2;
use Fluxion\Model2;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToManyField extends Field
{

    protected MnModel2 $_mn_model;

    private ?Field $_field = null;

    public ?bool $fake = true;

    protected string $_type_target = 'array';

    protected string $_name;

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function setModel(Model2 $model): void
    {
        $this->_model = $model;
    }

    /** @throws CustomException */
    public function __construct(public string  $class_name,
                                public bool    $inverted = false,
                                public bool    $real = false,
                                public bool    $show = false,
                                public ?string $type = null,
                                public ?array  $filter = null,
                                public ?string $label = null,
                                public ?string $mask_class = null,
                                public ?bool   $required = false,
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

    public function translate(mixed $value): array
    {

        if (empty($value)) {
            return [];
        }

        return $value;

    }

    public function validate(mixed &$value): bool
    {

        if (!parent::validate($value)) {
            return false;
        }

        return is_array($value);

    }

    /** @throws CustomException */
    public function initialize(): void
    {

        $this->_type = $this->_field->getType();

        parent::initialize();

    }

    public function isManyToMany(): bool
    {
        return true;
    }

}
