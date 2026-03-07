<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\FormField;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IframeField extends StringField
{

    protected string $_type = self::TYPE_TEXT;

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->type = 'iframe';

        return $form_field;

    }

}
