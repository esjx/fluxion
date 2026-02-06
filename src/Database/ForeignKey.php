<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\Model2;
use Fluxion\CustomException;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey
{

    private ?Model2 $_model = null;
    private ?Model2 $_reference_model = null;
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

    public function getModel(): Model2
    {
        return $this->_model;
    }

    public function getReferenceModel(): Model2
    {
        return $this->_reference_model;
    }

    public function getField(): Field
    {
        return $this->_field;
    }

    /** @throws CustomException */
    public function __construct(public string  $class_name,
                                public bool    $real = false,
                                public bool    $show = false,
                                public bool    $inverted = false,
                                public ?string $type = null,
                                public ?array  $filter = null)
    {

        $class = new $class_name;

        if (!$class instanceof Model2) {
            throw new CustomException(message: "Classe '$class_name' não é Model", log: false);
        }

        if (!is_null($this->type)
            && !in_array($this->type, ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION', 'SET DEFAULT'])) {
            throw new CustomException(message: "Tipo de relacionamento '$this->type' inválido", log: false);
        }

        $this->_reference_model = $class;

        $this->_field = $class->getFieldId();

    }

    /** @throws CustomException */
    public function initialize(): void
    {

        $class = get_class($this->_model);

        $field = $this->_model->getFields()[$this->_name];

        if ($field->getType() != $this->_field->getType()) {
            throw new CustomException(message: "Tipo do campo '$class:$this->_name ({$field->getType()})' diferente da chave estrangeira ({$this->_field->getType()}).", log: false);
        }

        if ($field->getType() == 'string' && $field->max_length != $this->_field->max_length) {
            throw new CustomException(message: "Tamanho do campo '$class:$this->_name' ($field->max_length) diferente da chave estrangeira ({$this->_field->max_length}).", log: false);
        }

    }

}
