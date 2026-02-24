<?php
namespace Fluxion\Database\Field;

use BackedEnum;
use Fluxion\{Color, Exception};
use Fluxion\Database\{FormField};

trait StaticChoices
{

    protected bool $created = false;

    /**
     * @throws Exception
     */
    public function createChoices(): void
    {

        if ($this->created) {
            return;
        }

        if (is_null($this->choices)) {
            $this->choices = [];
        }

        if (is_null($this->choices_colors)) {
            $this->choices_colors = [];
        }

        $class_name = $this->class_name;

        if (!is_null($class_name)) {

            if (!enum_exists($class_name)) {
                throw new Exception("Classe '$class_name' não existe ou não é um Enum!");
            }

            /** @var BackedEnum $class_name */
            foreach ($class_name::cases() as $case) {

                $test = false;

                if ($this->multiple && is_array($this->_value) && !in_array($case->value, $this->_value)) {
                    $test = true;
                }

                if (!$this->multiple && $case->value != $this->_value) {
                    $test = true;
                }

                if ($test) {
                    foreach ($this->filters as $key => $value) {
                        if (method_exists($case, $key) && $case->$key() != $value) {
                            continue 2;
                        }
                    }
                }

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

        $this->created = true;

    }

    public function getFormField(array $extras = [], ?string $route = null): FormField
    {

        /** @noinspection PhpMultipleClassDeclarationsInspection */
        $form_field = parent::getFormField($extras);

        $this->createChoices();

        foreach ($this->choices as $key => $label) {

            if ($this->_type == self::TYPE_STRING) {
                $key = (string) $key;
            }

            elseif (is_string($this->choices_colors[$key] ?? null)) {
                $this->choices_colors[$key] = Color::tryFrom($this->choices_colors[$key]);
            }

            $form_field->addChoice(
                value: $key,
                label: $label,
                color: $this->choices_colors[$key] ?? null
            );

        }

        $form_field->type = ($this->radio) ? 'radio' : 'choices';
        $form_field->inline = $this->inline;
        $form_field->multiple = $this->multiple;

        return $form_field;

    }

    /**
     * @throws Exception
     */
    public function getAuditValue(mixed $value): string
    {

        if (empty($value)) {
            return '<span class="text-pink"><i>(Vazio)</i></span>';
        }

        $this->createChoices();

        $items = [];

        if (!$this->multiple) {
            return $this->choices[$value] ?? $value;
        }

        foreach ($value as $k) {
            $items[] = $this->choices[$k] ?? $k;
        }

        return implode(', ', $items);

    }

}
