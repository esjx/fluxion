<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MailField extends StringField
{

    public ?string $pattern = '/^[\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,4}$/';

    public function validate(mixed &$value): bool
    {

        if (!parent::validate($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
        }

        else {
            $value = mb_strtolower($value);
        }

        return true;

    }

}
