<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Mask\Cnpj;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CnpjField extends StringField
{

    public ?string $validator_type = 'CNPJ';
    public ?string $text_transform = 'uppercase';
    public ?int $max_length = 14;

    public function getAuditValue(mixed $value): string
    {

        if (empty($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return Cnpj::mask($value);

    }

    public function getExportValue(mixed $value): string
    {

        if (empty($value)) {
            return '';
        }

        return Cnpj::mask($value);

    }

}
