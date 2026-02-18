<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\{Field, FormField};
use Fluxion\{Time};

#[Attribute(Attribute::TARGET_PROPERTY)]
class GeographyField extends Field
{

    protected string $_type = self::TYPE_GEOGRAPHY;
    protected string $_type_target = 'string';

    public ?bool $required = false;

    public function __construct(public ?bool $protected = false,
                                public ?bool $fake = false)
    {
        parent::__construct();
    }

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->type = 'text';

        return $form_field;

    }

}
