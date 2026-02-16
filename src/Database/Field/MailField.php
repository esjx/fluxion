<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MailField extends StringField
{

    public ?string $pattern = '/^[\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,4}$/';
    public ?string $text_transform = 'lowercase';

}
