<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Mask\Cpf;

#[Attribute(Attribute::TARGET_PROPERTY)]
class CpfField extends StringField
{

    public ?string $validator_type = 'CPF';
    public ?string $text_transform = 'uppercase';
    public ?int $max_length = 11;

    public function getAuditValue(mixed $value): string
    {

        if (empty($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return Cpf::mask($value);

    }

    public function getExportValue(mixed $value): string
    {

        if (empty($value)) {
            return '';
        }

        return Cpf::mask($value);

    }

}
