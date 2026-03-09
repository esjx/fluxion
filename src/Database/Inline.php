<?php
namespace Fluxion\Database;

use Fluxion\{Database\Field\ForeignKeyField, FluxionException, Model};

class Inline
{

    protected ?Model $_inline_model = null;

    public function getInlineModel(): Model
    {
        return $this->_inline_model;
    }

    /**
     * @throws FluxionException
     */
    public function getInlineField(Model $model): string
    {

        $il_model = $this->_inline_model;

        $class_name = get_class($model);
        $il_class_name = get_class($il_model);

        $class_name_fk = null;

        $field_id = $model->getFieldId();

        if ($field_id instanceof ForeignKeyField) {
            $class_name_fk = get_class($field_id->getReferenceModel());
        }

        # Identifica o campo de referência

        $il_field_ref = null;

        foreach ($il_model->getForeignKeys() as $key => $fk) {

            if (get_class($fk->getReferenceModel()) == $class_name
                || get_class($fk->getReferenceModel()) == $class_name_fk) {

                $il_field_ref = $key;
                break;

            }

        }

        if (is_null($il_field_ref)) {
            throw new FluxionException(message: "Classe '$il_class_name' não possui referência à classe '$class_name'");
        }

        if (in_array($il_field_ref, $this->fields)) {
            throw new FluxionException("Campo '$il_class_name:$il_field_ref' é a chave primária de '$class_name'");
        }

        return $il_field_ref;

    }

    /**
     * @throws FluxionException
     */
    public function __construct(public string  $class_name,
                                public array   $fields,
                                public ?string $id = null,
                                public ?string $title = null,
                                public ?string $not_found_message = null,
                                public ?bool   $insert = null,
                                public ?bool   $delete = null,
                                public array   $args = [],
                                public array   $filters = [],
                                public ?int    $max_items = null)
    {

        if (!class_exists($class_name)) {
            throw new FluxionException("Classe $class_name não encontrada");
        }

        $this->_inline_model = new $class_name;

        if (count($fields) == 0) {
            throw new FluxionException("É necessário informar pelo menos um campo");
        }

        foreach ($fields as $field) {
            if (!property_exists($this->_inline_model, $field)) {
                throw new FluxionException("Campo $class_name:$field não exite");
            }
        }

    }

}
