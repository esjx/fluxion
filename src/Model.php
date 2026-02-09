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

                $this->_table = $instance;

            }

            elseif ($instance instanceof Crud) {

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

        $class = get_class($this);
        $id = null;

        foreach ($this->getPrimaryKeys() as $key => $primary_key) {

            if (is_null($this->$key)) {
                return $class . ' (Novo)';
            }

            if (is_null($id)) {
                $id .= ' #' . $this->$key;
            }

            else {
                $id .= '/' . $this->$key;
            }

        }

        return $class . $id;

    }

    public function changeState(State $state): void {}

    /**
     * @throws Exception
     */
    public static function loadById(mixed $id): self
    {

        $class = get_called_class();

        /** @var self $obj */
        $obj = new $class();

        $primary_keys = $obj->getPrimaryKeys();

        if (count($primary_keys) == 0) {
            throw new Exception("Model '$class' não possui chave primária definida");
        }

        if (!is_array($id) && count($primary_keys) == 1) {
            $id = [$obj->getFieldId()->getName() => $id];
        }

        $query = $obj->query();

        foreach ($primary_keys as $key => $primary_key) {

            $value = $id[$key]
                ?? throw new Exception("Valor para o campo '$key' não informado");

            $query = $query->filter($key, $value);

        }

        return $query->firstOrNew();

    }

}
