<?php
namespace Fluxion\Database\Field;

use Fluxion\{Color, Exception};
use Fluxion\Query\{QuerySql};
use Fluxion\Database\{FormField};

trait DynamicChoices
{

    /**
     * @throws Exception
     */
    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        $form_field = parent::getFormField($extras);

        $pre = 30;
        $i = 0;
        $extra = false;

        $query = $this->_reference_model->query();
        $field_id = $this->_reference_model->getFieldId();
        $field_id_name = $field_id->getName();

        # Ordenar

        $query = $this->_reference_model->order($query);

        # Filtrar

        if (count($this->filters) > 0) {
            $query = $query->filter(QuerySql::_and($this->filters));
        }

        $field_color_name = '';
        foreach ($this->getReferenceModel()->getFields() as $f) {
            if ($f instanceof ColorField) {
                $field_color_name = $f->getName();
                break;
            }
        }

        if (!empty($this->_value)) {

            foreach ((clone $query)->filter($field_id_name, $this->_value)->select() as $row) {

                $pos = array_search($row->$field_id_name, $extras);

                if ($pos !== false) {
                    array_splice($extras, $pos, 1);
                }

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

            $query = $query->exclude($field_id_name, $this->_value);

        }

        if ($form_field->enabled) {

            foreach ((clone $query)->limit($pre + 1)->select() as $row) {

                if (++$i > $pre) {
                    $extra = true;
                    break;
                }

                $pos = array_search($row->$field_id_name, $extras);

                if ($pos !== false) {
                    array_splice($extras, $pos, 1);
                }

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

        }

        if (count($extras) > 0) {

            foreach ((clone $query)->filter($field_id_name, $extras)->select() as $row) {

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

        }

        $form_field->type = 'choices';
        $form_field->multiple = $this->multiple;

        if (!is_null($this->typeahead)) {
            $form_field->type = 'typeahead';
            $form_field->typeahead = $this->typeahead;
        }

        elseif ($extra) {
            $form_field->type = 'typeahead';
            $form_field->typeahead = "$route/typeahead/{$this->getName()}";
        }

        return $form_field;

    }

}
