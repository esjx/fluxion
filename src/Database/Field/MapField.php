<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\FormField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MapField extends StringField
{

    protected string $_type = self::TYPE_TEXT;

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->type = 'map';

        return $form_field;

    }

}
