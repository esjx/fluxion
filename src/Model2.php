<?php
namespace Fluxion;

use ReflectionClass;

abstract class Model2
{

    /** @var array<string, Database\Field> */
    private array $_fields = [];

    /** @var array<string, Database\Searchable> */
    private array $_searchable = [];

    /** @var array<string, Database\Filterable> */
    private array $_filterable = [];

    /** @var array<string, Database\PrimaryKey> */
    private array $_primary_keys = [];

    /** @var array<string, Database\ForeignKey> */
    private array $_foreign_keys = [];

    /** @var array<string, Database\Typeahead> */
    private array $_typeahead = [];
    private ?Database\Table $_table = null;

    /** @throws CustomException */
    public function __construct()
    {

        $reflection = new ReflectionClass(get_class($this));

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

                if ($instance instanceof Database\Field) {

                    $instance->setName($name);

                    $this->_fields[$name] = $instance;

                    if ($property->isInitialized($this)) {

                        $instance->setValue($this->$name);

                    }

                    unset($this->$name);

                }

                elseif ($instance instanceof Database\Searchable) {

                    $instance->setName($name);

                    $this->_searchable[$name] = $instance;

                }

                elseif ($instance instanceof Database\Filterable) {

                    $instance->setName($name);

                    $this->_filterable[$name] = $instance;

                }

                elseif ($instance instanceof Database\PrimaryKey) {

                    $instance->setName($name);

                    $this->_primary_keys[$name] = $instance;

                }

                elseif ($instance instanceof Database\ForeignKey) {

                    $instance->setName($name);

                    $this->_foreign_keys[$name] = $instance;

                }

                elseif ($instance instanceof Database\Typeahead) {

                    $instance->setName($name);

                    $this->_typeahead[$name] = $instance;

                }

                if ($instance instanceof Database\Field) {

                    $instance->setTypeProperty($property->getType());
                    $instance->setModel($this);
                    $instance->initialize();

                }

            }

        }

        # Carrega os atributos do modelo

        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {

            $instance = $attribute->newInstance();

            if ($instance instanceof Database\Table) {

                $this->_table = $instance;

            }

        }

        # Ajusta as chaves primárias

        foreach ($this->_primary_keys as $key => $primary_key) {

            $this->_fields[$key]->primary_key = true;

        }

        # Ajusta as chaves estrangeiras

        foreach ($this->_foreign_keys as $key => &$foreign_key) {

            $foreign_key->setModel($this);
            $foreign_key->initialize();

            $this->_fields[$key]->foreign_key = $foreign_key;

        }

    }

    /** @throws CustomException */
    public function __set($name, $value)
    {

        if (isset($this->_fields[$name])) {
            $this->_fields[$name]->setValue($value);
        }

    }

    public function __get($name)
    {

        if (isset($this->_fields[$name])) {
            return $this->_fields[$name]->getValue();
        }

        return null;

    }

    public function changeState($state)
    {

    }

    public function getTable(): ?Database\Table
    {
        return $this->_table;
    }

    /** @return array<string, Database\Field> */
    public function getFields(): array
    {
        return $this->_fields;
    }

    /** @return array<string, Database\PrimaryKey> */
    public function getPrimaryKeys(): array
    {
        return $this->_primary_keys;
    }

    public function __toString(): string
    {
        return get_class($this);
    }

}
