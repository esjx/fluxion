<?php
namespace Fluxion\Database;

use stdClass;
use Fluxion\{Config, Exception, Model, Permission, State};

class FormInline
{

    public static int $sequence = 0;

    public string $id;
    public string $title;
    public string $not_found_message;
    public int $max_itens;
    public ?bool $insert;
    public ?bool $delete;
    public array $fields;
    public array $items;

    public string $no_itens; #TODO: Remover
    public array $itens; #TODO: Remover

    /**
     * @throws Exception
     */
    public function __construct(Model $model, Inline $inline, ?bool $save = null)
    {

        # Dados e permissões

        $auth = Config::getAuth();

        $il_model = $inline->getInlineModel();
        $il_crud = $il_model->getCrud();

        $field_id = $model->getFieldId()->getName();

        $il_model->changeState(State::INLINE, $inline->args);

        # Identifica o campo de referência

        $il_field_ref = $inline->getInlineField($model);

        # Valores atuais

        $inline_itens = [];

        $extras = [];

        if (!is_null($model->$field_id)) {

            foreach ($il_model::filter($il_field_ref, $model->$field_id)->select() as $item) {

                $item->changeState(State::INLINE_VIEW, $inline->args);

                $data = new stdClass();

                $data->__id = $item->id();

                foreach ($inline->fields as $key) {

                    $il_field = $il_model->getField($key);

                    if ($il_field->isForeignKey() || $il_field->isManyToMany() || $il_field->isManyChoices()) {

                        if (!array_key_exists($key, $extras)) {
                            $extras[$key] = [];
                        }

                        if (!in_array($item->$key, $extras[$key])) {
                            $extras[$key][] = $item->$key;
                        }

                    }

                    $data->$key = $item->$key;

                }

                $inline_itens[] = $data;

            }

        }

        # Campos

        if (is_null($save)) {
            $save = $auth->hasPermission($model, Permission::UPDATE);
        }

        /** @var FormField[] $inline_fields */
        $inline_fields = [];

        $inline_fields[] = new FormField(
            visible: false,
            name: '__id',
            type: 'string',
        );

        foreach ($inline->fields as $key) {

            $il_field = $il_model->getField($key);

            $form_field = $il_field->getFormField($extras[$key] ?? []);

            if (!$save) {
                $form_field->enabled = false;
            }

            $inline_fields[] = $form_field;

        }

        # Atualiza os dados

        $this->id = $inline->id ?? 'inline_' . self::$sequence++;
        $this->title = $inline->title ?? $il_crud->plural_title;
        $this->not_found_message = $inline->not_found_message ?? $il_crud->not_found_message;
        $this->max_itens = $inline->max_itens ?? 20;
        $this->insert = $inline->insert ?? $auth->hasPermission($il_model, Permission::INSERT);
        $this->delete = $inline->delete ?? $auth->hasPermission($il_model, Permission::DELETE);
        $this->fields = $inline_fields;
        $this->items = $inline_itens;

        # TODO: Remover
        $this->no_itens = $inline->not_found_message ?? $il_crud->not_found_message;
        $this->itens = $inline_itens;

    }

}
