<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CpfField extends StringField
{

    public ?string $validator_type = 'CPF';
    public ?string $text_transform = 'uppercase';
    public ?int $max_length = 11;

}
