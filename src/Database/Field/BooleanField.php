<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\{Field};

#[Attribute(Attribute::TARGET_PROPERTY)]
class BooleanField extends Field
{

    protected string $_type = self::TYPE_BOOLEAN;
    protected string $_type_target = 'bool';

    public function __construct(public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public mixed $default = null,
                                public bool  $default_literal = false,
                                public ?bool $fake = false,
                                public ?bool $enabled = true,
                                bool $needs_audit = true)
    {

        $this->_needs_audit = $needs_audit;

        parent::__construct();

    }

    public function translate(mixed $value): ?bool
    {

        if (is_null($value)) {
            return null;
        }

        return !!$value;

    }

    public function validate(mixed &$value): bool
    {

        if ($this->required && is_null($value)) {
            return false;
        }

        return true;

    }

    public function format(mixed $value): bool
    {
        return !!$value;
    }

    public function getAuditValue(mixed $value): string
    {

        if (is_null($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return ($value) ? '&#x26AB;' : '&#x26AA';

    }

    public function getExportValue(mixed $value): string
    {

        if (is_null($value)) {
            return '';
        }

        return ($value) ? 'S' : 'N';

    }

}
