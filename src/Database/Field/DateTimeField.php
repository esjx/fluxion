<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\{Time};

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateTimeField extends DateField
{

    protected string $_type = self::TYPE_DATETIME;

    protected string $date_format = 'Y-m-d H:i:s';

    public function getAuditValue(mixed $value): string
    {

        if (empty($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return Time::convert($value, 'd/m/Y H:i:s');

    }

    public function getExportValue(mixed $value): string
    {

        if (empty($value)) {
            return '';
        }

        return Time::convert($value, 'd/m/Y H:i:s');

    }

}
