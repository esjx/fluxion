<?php
namespace Fluxion\Database\Field;

use Attribute;
use ReflectionException;
use Fluxion\{Exception, ManyChoicesModel, Model, Color};
use Fluxion\Database\{Field, FormField};

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyChoicesField extends Field
{

    use Choices;

    protected ManyChoicesModel $_mc_model;

    public ?bool $fake = true;
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
    public function getManyChoicesModel(): ManyChoicesModel
    {

        if (empty($this->_mc_model)) {

            $this->_mc_model = new ManyChoicesModel($this->_model, $this->_name);

        }

        return $this->_mc_model;

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

            $mn_model = new ManyChoicesModel(new $class(), $this->_name);

            $field_id = $this->_model->getFieldId();

            $this->_value = $mn_model->load($field_id->getValue());

        }

    }

    /**
     * @throws Exception
     */
    public function __construct(array          $choices,
                                public ?array  $choices_colors = null,
                                string         $choices_type = 'string',
                                public ?string $class_name = null,
                                public bool    $show = false,
                                public ?string $type = null,
                                public ?array  $filter = null,
                                public ?bool   $required = false,
                                public ?bool   $protected = false,
                                public ?bool   $readonly = false,
                                public ?bool   $enabled = true)
    {

        $this->choices = $choices;

        if (!in_array($choices_type, [self::TYPE_STRING, self::TYPE_INTEGER])) {
            throw new Exception("Tipo de opções '$choices_type' inválido!");
        }

        $this->_type = $choices_type;

        $this->createChoices();

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

        parent::initialize();

    }

    public function isManyChoices(): bool
    {
        return true;
    }

}
