<?php
namespace Fluxion\Database\Field;

use Attribute;
use ReflectionException;
use Fluxion\{Exception, ManyToManyModel, Model};
use Fluxion\Database\{Field};

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToManyField extends Field
{

    use DynamicChoices;

    protected ManyToManyModel $_mn_model;

    public ?bool $assistant_table = true;
    public ?bool $multiple = true;

    protected string $_type_target = 'array';

    public function setModel(Model $model): void
    {
        $this->_model = $model;
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function getManyToManyModel(): ManyToManyModel
    {

        if (empty($this->_mn_model)) {

            $class_name = $this->_model;

            $this->_mn_model = new ManyToManyModel(new $class_name(), $this->_name, $this->inverted);

        }

        return $this->_mn_model;

    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function getValue($row = false): mixed
    {

        $this->update();

        if ($row) {
            return $this->_value;
        }

        $this->load();

        return $this->format($this->_value);

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function load(): void
    {

        if (is_null($this->_value) && !$this->_changed && $this->_model->isSaved()) {

            $class = get_class($this->_model);

            $mn_model = new ManyToManyModel(new $class(), $this->_name, $this->inverted);

            $field_id = $this->_model->getFieldId();

            $this->_value = $mn_model->load($field_id->getValue());

            $this->_saved_value = $this->_value;

        }

    }

    public function __construct(public string  $class_name,
                                public bool    $inverted = false,
                                public bool    $real = false,
                                public bool    $show = false,
                                public ?string $type = null,
                                public array   $filters = [],
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?bool   $enabled = true)
    {

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

    /** @throws Exception */
    public function initialize(): void
    {

        $class_name = $this->class_name;

        if ($class_name == get_class($this->_model)) {
            $class = clone $this->_model;
        }

        else {

            $class = new $class_name;

            if (!$class instanceof Model) {
                throw new Exception(message: "Classe '$class_name' não é Model", log: false);
            }

        }

        $this->_reference_model = $class;

        $this->_type = $class->getFieldId()->getType();

        parent::initialize();

    }

    public function isManyToMany(): bool
    {
        return !$this->fake;
    }

}
