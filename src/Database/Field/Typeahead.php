<?php
namespace Fluxion\Database\Field;

use Fluxion\Database\{FormField};
use Fluxion\Exception;

trait Typeahead
{

    /**
     * @throws Exception
     */
    public function getFormField(): FormField
    {

        $form_field = parent::getFormField();

        $pre = 30;
        $i = 0;
        $extra = false;

        $query = $this->_reference_model->query();
        $field_id = $this->_reference_model->getFieldId();
        $field_id_name = $field_id->getName();

        # Ordenar

        $query = $this->_reference_model->order($query);

        # Filtrar

        #TODO

        if (!empty($this->_value)) {

            foreach ((clone $query)->filter($field_id_name, $this->_value)->select() as $row) {

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string)$row
                );

            }

            $query = $query->exclude($field_id_name, $this->_value);

        }

        if ($form_field->enabled) {

            foreach ($query->limit($pre + 1)->select() as $row) {

                if (++$i > $pre) {
                    $extra = true;
                    break;
                }

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string)$row
                );

            }

        }

        $form_field->type = 'choices';
        $form_field->multiple = $this->multiple;

        if ($extra) {
            $form_field->type = 'typeahead';
            $form_field->typeahead = 'url'; #TODO
        }

        return $form_field;

    }

}
