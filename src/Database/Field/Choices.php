<?php
namespace Fluxion\Database\Field;

use BackedEnum;
use Fluxion\{Color, Exception};
use Fluxion\Database\{FormField};

trait Choices
{

    /**
     * @throws Exception
     */
    public function createChoices(): void
    {

        $class_name = $this->class_name;

        if (!is_null($class_name)) {

            if (!enum_exists($class_name)) {
                throw new Exception("Classe '$class_name' não existe ou não é um Enum!");
            }

            /** @var BackedEnum $class_name */
            foreach ($class_name::cases() as $case) {

                if (method_exists($case, 'label')) {
                    $this->choices[$case->value] = $case->label();
                }

                else {
                    $this->choices[$case->value] = $case->name;
                }

                if (method_exists($case, 'color')) {

                    $cor = $case->color();

                    if (!is_null($cor) && !$cor instanceof Color) {
                        throw new Exception("Valor '$cor' não é Color!");
                    }

                    $this->choices_colors[$case->value] = $cor;

                }

            }

        }

    }

    public function getFormField(): FormField
    {

        $form_field = parent::getFormField();

        foreach ($this->choices as $key => $label) {

            if ($this->_type == self::TYPE_STRING) {
                $key = (string) $key;
            }

            if (!$this->radio) {
                $this->choices_colors[$key] = null;
            }

            elseif (is_string($this->choices_colors[$key])) {
                $this->choices_colors[$key] = Color::tryFrom($this->choices_colors[$key]);
            }

            $form_field->addChoice(
                value: $key,
                label: $label,
                color: $this->choices_colors[$key]
            );

        }

        $form_field->type = ($this->radio) ? 'radio' : 'choices';
        $form_field->inline = $this->inline;

        return $form_field;

    }

}
