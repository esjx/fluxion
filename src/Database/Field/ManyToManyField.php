<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\CustomException;
use Fluxion\MnModel;
use Fluxion\Model;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToManyField extends Field
{

    protected MnModel $_mn_model;

    private ?Field $_field = null;

    public ?bool $fake = true;

    protected string $_type_target = 'array';

    protected string $_name;

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function setModel(Model $model): void
    {
        $this->_model = $model;
    }

    /**
     * @throws CustomException
     */
    public function getMnModel(): MnModel
    {

        if (empty($this->_mn_model)) {

            $this->_mn_model = new MnModel($this->_model, $this->_name, $this->inverted);

        }

        return $this->_mn_model;

    }

    /**
     * @throws CustomException
     */
    public function getValue($row = false): mixed
    {

        $this->update();

        if ($row) {
            return $this->_value;
        }

        if (is_null($this->_value) && $this->_model->isSaved()) {

            $class = get_class($this->_model);

            $mn_model = new MnModel(new $class(), $this->_name, $this->inverted);

            $field_id = $this->_model->getFieldId();

            return $mn_model->load($field_id->getValue());

        }

        return $this->format($this->_value);

    }

    /** @throws CustomException */
    public function __construct(public string  $class_name,
                                public bool    $inverted = false,
                                public bool    $real = false,
                                public bool    $show = false,
                                public ?string $type = null,
                                public ?array  $filter = null,
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?string $column_name = null)
    {

        $class = new $class_name;

        if (!$class instanceof Model) {
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
