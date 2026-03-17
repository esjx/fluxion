<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\{Field, FormField};
use Fluxion\{FluxionException, Time};

#[Attribute(Attribute::TARGET_PROPERTY)]
class DateField extends Field
{

    protected string $_type = self::TYPE_DATE;

    protected string $date_format = 'Y-m-d';

    public function __construct(public ?bool           $required = false,
                                public ?bool           $protected = false,
                                public ?bool           $readonly = false,
                                public ?bool           $primary_key = false,
                                public null|int|string $min_value = null,
                                public null|int|string $max_value = null,
                                public mixed           $default = null,
                                public bool            $default_literal = false,
                                public ?bool           $fake = false,
                                public ?bool           $null_if_invalid = false,
                                public ?bool           $enabled = true,
                                bool $needs_audit = true)
    {

        $this->_needs_audit = $needs_audit;

        parent::__construct();

    }

    /**
     * @throws FluxionException
     */
    public function validate(mixed &$value): bool
    {

        if (is_string($value) && !empty($value)) {
            $value = trim($value);
        }

        if (!parent::validate($value)) {
            return false;
        }

        if (empty($value)) {
            $value = null;
        }

        else {

            $new_value = Time::convert($value, $this->date_format);

            if (is_null($new_value) && !$this->null_if_invalid) {
                throw new FluxionException("Valor '$value' inválido para o campo '$this->_name'");
            }

            else {
                $value = $new_value;
            }

        }

        return true;

    }

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->value = Time::convert($this->_value, 'd/m/Y H:i:s');

        $form_field->min_date = Time::convert($this->min_value, 'd/m/Y');
        $form_field->max_date = Time::convert($this->max_value, 'd/m/Y');

        return $form_field;

    }

    public function getAuditValue(mixed $value): string
    {

        if (empty($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        return Time::convert($value, 'd/m/Y');

    }

    public function getExportValue(mixed $value): string
    {

        if (empty($value)) {
            return '';
        }

        return Time::convert($value, 'd/m/Y');

    }

}
