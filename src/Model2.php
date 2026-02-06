<?php
namespace Fluxion;

use Generator;
use ReflectionClass;
use Fluxion\Query\{Query2, QuerySql};

abstract class Model2
{

    protected array $_data = [];

    public function getData(): array
    {
        return $this->_data;
    }

    /** @var array<string, Database\Field> */
    protected array $_fields = [];

    /** @var array<string, Database\Searchable> */
    protected array $_searchable = [];

    /** @var array<string, Database\Filterable> */
    protected array $_filterable = [];

    /** @var array<string, Database\PrimaryKey> */
    protected array $_primary_keys = [];

    /** @var array<string, Database\ForeignKey> */
    protected array $_foreign_keys = [];

    /** @var array<string, Database\ManyToMany> */
    protected array $_many_to_many = [];

    /** @var array<string, Database\Typeahead> */
    protected array $_typeahead = [];
    protected ?Database\Table $_table = null;

    protected ?string $comment = null;

    #[Database\FloatField(protected: true, fake: true)]
    public ?float $total = null;

    public function setComment(?string $comment): void {
        $this->comment = $comment;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

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

                elseif ($instance instanceof Database\ManyToMany) {

                    $instance->setName($name);

                    $this->_many_to_many[$name] = $instance;

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

        foreach ($this->_foreign_keys as $key => $foreign_key) {

            $foreign_key->setModel($this);
            //$foreign_key->initialize();

            $this->_fields[$key]->foreign_key = $foreign_key;

        }

        # Ajusta os campos muitos para muitos

        foreach ($this->_many_to_many as $key => $many_to_many) {

            $many_to_many->setModel($this);
            //$many_to_many->initialize();

            $this->_fields[$key]->fake = true;
            $this->_fields[$key]->many_to_many = $many_to_many;

        }

        # Inicializa os campos

        foreach ($this->_fields as $field) {
            $field->initialize();
        }

    }

    public function __isset($name): bool
    {
        return isset($this->_fields[$name]);
    }

    /** @throws CustomException */
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

    public function changeState(State $state): void
    {

    }

    public function isChanged(): bool
    {

        foreach ($this->_fields as $field) {
            if ($field->isChanged()) {
                return true;
            }
        }

        return false;

    }

    protected bool $saved = false;

    public function isSaved(): bool
    {
        return $this->saved;
    }

    public function setSaved(bool $saved): void
    {
        $this->saved = $saved;
    }

    public function onSave(): bool
    {
        return true;
    }

    public function onSaved(): void
    {

    }

    /**
     * @throws CustomException
     */
    public function save(): bool
    {

        $this->changeState(State::STATE_SAVE);

        if ($this->onSave() && Config2::getConnector()->save($this)) {

            $this->saved = true;

            $this->onSaved();

        }

        return false;

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

    /**
     * @throws CustomException
     */
    public function getField($name): ?Database\Field
    {

        if ($name == '*') {
            return null;
        }

        return $this->_fields[$name]
            ?? throw new CustomException("Campo '$name' não encontrado no modelo");

    }

    /** @return array<string, Database\PrimaryKey> */
    public function getPrimaryKeys(): array
    {
        return $this->_primary_keys;
    }

    /** @return array<string, Database\ForeignKey> */
    public function getForeignKeys(): array
    {
        return $this->_foreign_keys;
    }

    /** @return array<string, Database\ManyToMany> */
    public function getManyToMany(): array
    {
        return $this->_many_to_many;
    }

    public function __toString(): string
    {

        $class = get_class($this);
        $id = null;

        foreach ($this->_primary_keys as $key => $primary_key) {

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

    /** @return array<string, Connector\TableIndex> */
    public function getIndexes(): array
    {
        return [];
    }

    /** @throws CustomException */
    public function getFieldId(): Database\Field
    {

        $field = null;

        $class_name = get_class($this);

        foreach ($this->_primary_keys as $key => $primary_key) {

            if (!is_null($field)) {
                throw new CustomException(message: "Classe '$class_name' possui mais de uma chave primária", log: false);
            }

            $field = $this->_fields[$key];

        }

        if (is_null($field)) {
            throw new CustomException(message: "Classe '$class_name' não possui chave primária", log: false);
        }

        return $field;

    }

    public static function query(): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return new Query2($obj);

    }

    public static function only($field): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->only($field);

    }

    public static function addField($field): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->addField($field);

    }

    public static function count($field = '*', $name = 'total'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->count($field, $name);

    }

    public static function sum($field, $name = 'total'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->sum($field, $name);

    }

    public static function avg($field, $name = 'total'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->avg($field, $name);

    }

    public static function min($field, $name = 'total'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->min($field, $name);

    }

    public static function max($field, $name = 'total'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->sum($field, $name);

    }

    public static function filter(string|QuerySql $field, $value = null): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->filter($field, $value);

    }

    public static function filterIf(string|QuerySql $field, $value = null, $if = true): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->filterIf($field, $value, $if);

    }

    public static function exclude(string|QuerySql $field, $value = null): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->exclude($field, $value);

    }

    public static function excludeIf(string|QuerySql $field, $value = null, $if = true): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->excludeIf($field, $value, $if);

    }

    public static function orderBy($field, $order = 'ASC'): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->orderBy($field, $order);

    }

    public static function groupBy($field, $only = true): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->groupBy($field, $only);

    }

    /**
     * @throws CustomException
     */
    public static function limit($limit, $offset = 0): Query2
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->limit($limit, $offset);

    }

    /**
     * @throws CustomException
     */
    public static function select(): Generator
    {

        $class = get_called_class();
        $obj = new $class();

        return (new Query2($obj))->select();

    }

    /**
     * @throws CustomException
     */
    public static function loadById(mixed $id): self
    {

        $class = get_called_class();

        /** @var self $obj */
        $obj = new $class();

        $primary_keys = $obj->getPrimaryKeys();

        if (count($primary_keys) == 0) {
            throw new CustomException("Model '$class' não possui chave primária definida");
        }

        if (!is_array($id) && count($primary_keys) == 1) {
            $id = [$obj->getFieldId()->getName() => $id];
        }

        $query = $obj->query();

        foreach ($primary_keys as $key => $primary_key) {

            $value = $id[$key]
                ?? throw new CustomException("Valor para o campo '$key' não informado");

            $query = $query->filter($key, $value);

        }

        return $query->firstOrNew();

    }

}
