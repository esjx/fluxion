<?php
namespace Fluxion;

use Fluxion\Database\{Field, Table};
use Fluxion\Database\Field\{ForeignKeyField, ManyToManyField};

trait ModelFields
{

    # Dados iniciais para o banco de dados

    protected array $_data = [];

    public function getData(): array
    {
        return $this->_data;
    }

    # Dados para identificação da tabela e índices

    protected ?Table $_table = null;

    public function getTable(): ?Table
    {
        return $this->_table;
    }

    /** @return array<string, Connector\TableIndex> */
    public function getIndexes(): array
    {
        return [];
    }

    # Comentário para a tabela nos logs

    protected ?string $comment = null;

    public function getComment(): ?string {
        return $this->comment ?? get_class($this);
    }

    # Uso e manipulação dos campos

    /** @var array<string, Field> */
    protected array $_fields = [];

    public function id(): ?string
    {
        return implode(';', array_map(function ($field) {
            return $field->getValue();
        }, $this->getPrimaryKeys()));
    }

    public function getFieldsValues(): array
    {
        return array_map(function ($field) {
            return $field->getValue();
        }, $this->_fields);
    }

    /** @return array<string, Field> */
    public function getFields(): array
    {
        return $this->_fields;
    }

    /**
     * @throws Exception
     */
    public function getField($name): ?Field
    {

        if ($name == '*') {
            return null;
        }

        return $this->_fields[$name]
            ?? throw new Exception("Campo '$name' não encontrado no modelo");

    }

    /** @throws Exception */
    public function getFieldId(): Field
    {

        $field = null;

        $class_name = get_class($this);

        foreach ($this->getPrimaryKeys() as $key => $primary_key) {

            if (!is_null($field)) {
                throw new Exception(message: "Classe '$class_name' possui mais de uma chave primária", log: false);
            }

            $field = $this->_fields[$key];

        }

        if (is_null($field)) {
            throw new Exception(message: "Classe '$class_name' não possui chave primária", log: false);
        }

        return $field;

    }

    /** @return array<string, Field> */
    public function getIdentity(): array
    {
        return array_filter($this->_fields, function ($field) {
            return $field->isIdentity();
        });
    }

    /** @return array<string, Field> */
    public function getPrimaryKeys(): array
    {
        return array_filter($this->_fields, function ($field) {
            return $field->isPrimaryKey();
        });
    }

    /** @return array<string, ForeignKeyField> */
    public function getForeignKeys(): array
    {
        return array_filter($this->_fields, function ($field) {
            return $field->isForeignKey();
        });
    }

    /** @return array<string, ManyToManyField> */
    public function getManyToMany(): array
    {
        return array_filter($this->_fields, function ($field) {
            return $field->isManyToMany();
        });
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

}
