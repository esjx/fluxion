<?php
namespace Fluxion;

use ReflectionClass;
use Fluxion\Database\{Crud, Detail, Field, Table};
use Fluxion\Database\Field\{FloatField};

abstract class Model
{

    use ModelFields;
    use ModelQuery;
    use ModelCrud;
    use ModelSave;

    #[FloatField(protected: true, fake: true)]
    public ?float $total = null;

    /** @throws Exception */
    public function __construct()
    {

        $reflection = new ReflectionClass(get_class($this));

        # Carrega os atributos do modelo

        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {

            $instance = $attribute->newInstance();

            if ($instance instanceof Table) {

                $instance->initialize();

                $this->_table = $instance;

            }

            elseif ($instance instanceof Crud) {

                $instance->initialize($this);

                $this->_crud = $instance;

            }

        }

        # Carrega os atributos dos campos do modelo

        $properties = $reflection->getProperties();

        foreach ($properties as $property) {

            if (!$property->isPublic()) {
                continue;
            }

            $name = $property->getName();

            if (in_array($name, ['__id', '__deleted', '__created', '__inlines'])) {
                throw new Exception("Nome de campo '$name' é reservado!");
            }

            $attributes = $property->getAttributes();

            foreach ($attributes as $attribute) {

                $instance = $attribute->newInstance();

                if ($instance instanceof Field) {

                    $this->_fields[$name] = $instance;

                    if ($property->isInitialized($this)) {
                        $instance->setValue($this->$name);
                    }

                    $instance->setName($name);
                    $instance->setModel($this);
                    $instance->setTypeProperty($property->getType());
                    $instance->initialize();

                }

                elseif ($instance instanceof Detail) {

                    $this->_details[$name] = $instance;

                    $instance->setName($name);

                }

            }

            unset($this->$name);

        }

    }

    public function __isset($name): bool
    {
        return isset($this->_fields[$name]);
    }

    /** @throws Exception */
    public function __set($name, $value)
    {

        if (isset($this->_fields[$name])) {
            $this->_fields[$name]->setValue($value);
        }

    }

    public function __get($name): mixed
    {

        if (isset($this->_fields[$name])) {
            return $this->_fields[$name]->getValue();
        }

        return null;

    }

    public function __toString(): string
    {

        $crud_detail = $this->getCrud();

        if ($id = $this->id()) {
            return mb_strtoupper($crud_detail->title) . " #$id";
        }

        return mb_strtoupper($crud_detail->title) ;

    }

}
