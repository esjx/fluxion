<?php
namespace Fluxion\Database\Field;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CnpjField extends StringField
{

    public ?string $validator_type = 'CNPJ';
    public ?string $text_transform = 'uppercase';
    public ?int $max_length = 14;

}
