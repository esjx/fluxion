<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Database\Field;
use Fluxion\Database\FormField;
use Fluxion\Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PasswordField extends Field
{

    protected string $_type = self::TYPE_PASSWORD;

    public function __construct(public ?bool $required = false,
                                public ?bool $protected = false,
                                public ?bool $readonly = false,
                                public ?bool $enabled = true)
    {
        parent::__construct();
    }

    /** @throws Exception */
    public function validadePassword($password): bool
    {

        if (!password_verify($password, $this->_value)) {
            throw new Exception(message: "Senha inválida!", log: false);
        }

        if (password_needs_rehash($this->_value, PASSWORD_DEFAULT)) {
            $this->_value = password_hash($password, PASSWORD_DEFAULT);
            $this->_model->save();
        }

        return true;

    }

    public function translate(mixed $value): ?string
    {

        if (is_null($value) || $value === '') {
            return null;
        }

        return password_hash($value, PASSWORD_DEFAULT);

    }

    public function getFormField(array $extras = []): FormField
    {

        $form_field = parent::getFormField($extras);

        $form_field->value = null;

        return $form_field;

    }

}
