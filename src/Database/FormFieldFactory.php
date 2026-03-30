<?php
namespace Fluxion\Database;

use BackedEnum;
use Fluxion\{FluxionException, Color, Model, State};
use Fluxion\Query\{QuerySql};
use Fluxion\Database\Field\{ColorField};

class FormFieldFactory
{

    public static function string(string  $name,
                                  ?string $label = null,
                                  bool    $visible = true,
                                  bool    $enabled = true,
                                  int     $size = 12,
                                  bool    $required = false,
                                  string  $placeholder = '',
                                  ?string $pattern = null,
                                  ?string $text_transform = null,
                                  ?string $mask = null,
                                  ?bool   $mask_literal = false,
                                  ?int    $minlength = null,
                                  ?int    $maxlength = null,
                                  ?string $group_name = null,
                                  ?string $help = null,
                                  ?string $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'string',
            size: $size,
            required: $required,
            placeholder: $placeholder,
            pattern: $pattern,
            text_transform: $text_transform,
            mask: $mask,
            mask_literal: $mask_literal,
            minlength: $minlength,
            maxlength: $maxlength,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function text(string  $name,
                                ?string $label = null,
                                bool    $visible = true,
                                bool    $enabled = true,
                                int     $size = 12,
                                bool    $required = false,
                                string  $placeholder = '',
                                ?string $pattern = null,
                                ?string $text_transform = null,
                                ?int    $minlength = null,
                                ?int    $maxlength = null,
                                ?string $group_name = null,
                                ?string $help = null,
                                ?string $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'text',
            size: $size,
            required: $required,
            placeholder: $placeholder,
            pattern: $pattern,
            text_transform: $text_transform,
            minlength: $minlength,
            maxlength: $maxlength,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function html(string  $name,
                                ?string $label = null,
                                bool    $visible = true,
                                bool    $enabled = true,
                                int     $size = 12,
                                bool    $required = false,
                                string  $placeholder = '',
                                ?string $pattern = null,
                                ?string $text_transform = null,
                                ?int    $minlength = null,
                                ?int    $maxlength = null,
                                ?string $group_name = null,
                                ?string $help = null,
                                ?string $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'html',
            size: $size,
            required: $required,
            placeholder: $placeholder,
            pattern: $pattern,
            text_transform: $text_transform,
            minlength: $minlength,
            maxlength: $maxlength,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function iframe(string  $name,
                                  ?string $label = null,
                                  bool    $visible = true,
                                  bool    $enabled = true,
                                  int     $size = 12,
                                  bool    $required = false,
                                  string  $placeholder = '',
                                  ?string $pattern = null,
                                  ?string $text_transform = null,
                                  ?int    $minlength = null,
                                  ?int    $maxlength = null,
                                  ?string $group_name = null,
                                  ?string $help = null,
                                  ?string $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'iframe',
            size: $size,
            required: $required,
            placeholder: $placeholder,
            pattern: $pattern,
            text_transform: $text_transform,
            minlength: $minlength,
            maxlength: $maxlength,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function date(string  $name,
                                ?string $label = null,
                                bool    $visible = true,
                                bool    $enabled = true,
                                int     $size = 12,
                                bool    $required = false,
                                string  $placeholder = '',
                                ?string $pattern = null,
                                ?string $text_transform = null,
                                ?int    $min = null,
                                ?int    $max = null,
                                ?string $group_name = null,
                                ?string $help = null,
                                ?string $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'date',
            size: $size,
            min: $min,
            max: $max,
            required: $required,
            placeholder: $placeholder,
            pattern: $pattern,
            text_transform: $text_transform,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function integer(string  $name,
                                   ?string $label = null,
                                   bool    $visible = true,
                                   bool    $enabled = true,
                                   int     $size = 12,
                                   ?int    $min = null,
                                   ?int    $max = null,
                                   bool    $required = false,
                                   string  $placeholder = '',
                                   ?string $group_name = null,
                                   ?string $help = null,
                                   ?int     $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'integer',
            size: $size,
            min: $min,
            max: $max,
            required: $required,
            placeholder: $placeholder,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function boolean(string  $name,
                                   ?string $label = null,
                                   bool    $visible = true,
                                   bool    $enabled = true,
                                   int     $size = 12,
                                   bool    $required = false,
                                   ?string $group_name = null,
                                   ?string $help = null,
                                   ?int     $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'boolean',
            size: $size,
            required: $required,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    public static function upload(string  $name,
                                  ?string $label = null,
                                  bool    $visible = true,
                                  bool    $enabled = true,
                                  int     $size = 12,
                                  int     $max_size = 1024 * 1024 * 3,
                                  bool    $required = false,
                                  ?string $group_name = null,
                                  ?string $help = null,
                                  ?int     $value = null): FormField
    {

        return new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            type: 'upload',
            size: $size,
            max_size: $max_size,
            required: $required,
            group_name: $group_name,
            help: $help,
            value: $value
        );

    }

    /**
     * @throws FluxionException
     */
    public static function staticChoices(string  $name,
                                         ?string $class_name = null,
                                         ?string $label = null,
                                         bool    $string = false,
                                         bool    $radio = false,
                                         bool    $inline = false,
                                         bool    $multiple = false,
                                         bool    $visible = true,
                                         bool    $enabled = true,
                                         int     $size = 12,
                                         ?int    $min = null,
                                         ?int    $max = null,
                                         bool    $required = false,
                                         string  $placeholder = '',
                                         ?string $group_name = null,
                                         ?string $help = null,
                                         array   $filters = [],
                                         array   $choices = [],
                                         array   $choices_colors = [],
                                         mixed   $value = null): FormField
    {

        $form_field = new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            size: $size,
            min: $min,
            max: $max,
            required: $required,
            placeholder: $placeholder,
            group_name: $group_name,
            help: $help,
            value: $value
        );

        # Cria os itens do select

        if (!is_null($class_name)) {

            if (!enum_exists($class_name)) {
                throw new FluxionException("Classe '$class_name' não existe ou não é um Enum!");
            }

            /** @var BackedEnum $class_name */
            foreach ($class_name::cases() as $case) {

                $test = false;

                if ($multiple && is_array($value) && !in_array($case->value, $value, true)) {
                    $test = true;
                }

                if (!$multiple && $case->value !== $value) {
                    $test = true;
                }

                if ($test) {
                    foreach ($filters as $key => $value) {
                        if (method_exists($case, $key) && $case->$key() != $value) {
                            continue 2;
                        }
                    }
                }

                if (method_exists($case, 'label')) {
                    $choices[$case->value] = $case->label();
                }

                else {
                    $choices[$case->value] = $case->name;
                }

                if (method_exists($case, 'color')) {

                    $cor = $case->color();

                    if (!is_null($cor) && !$cor instanceof Color) {
                        throw new FluxionException("Valor '$cor' não é Color!");
                    }

                    $choices_colors[$case->value] = $cor;

                }

            }

        }

        # Adiciona os itens ao select

        foreach ($choices as $key => $label) {

            if ($string) {
                $key = (string) $key;
            }

            elseif (is_string($choices_colors[$key] ?? null)) {
                $choices_colors[$key] = Color::tryFrom($choices_colors[$key]);
            }

            $form_field->addChoice(
                value: $key,
                label: $label,
                color: $choices_colors[$key] ?? null
            );

        }

        $form_field->type = ($radio) ? 'radio' : 'choices';
        $form_field->inline = $inline;
        $form_field->multiple = $multiple;

        return $form_field;

    }

    /**
     * @throws FluxionException
     */
    public static function dynamicChoices(string  $name,
                                          string  $class_name,
                                          ?string $label = null,
                                          bool    $radio = false,
                                          bool    $multiple = false,
                                          bool    $visible = true,
                                          bool    $enabled = true,
                                          int     $size = 12,
                                          ?int    $min = null,
                                          ?int    $max = null,
                                          bool    $required = false,
                                          string  $placeholder = '',
                                          ?string $group_name = null,
                                          ?string $help = null,
                                          ?string $typeahead = null,
                                          array   $filters = [],
                                          array   $extras = [],
                                          mixed   $value = null): FormField
    {

        $form_field = new FormField(
            name: $name,
            label: $label,
            visible: $visible,
            enabled: $enabled,
            size: $size,
            min: $min,
            max: $max,
            required: $required,
            placeholder: $placeholder,
            group_name: $group_name,
            help: $help,
            value: $value
        );

        $pre = 30;
        $i = 0;
        $extra = false;

        $model = new $class_name();

        if (!$model instanceof Model) {
            throw new FluxionException("Classe '$class_name' não estende Model!");
        }

        $query = $model->query();
        $field_id = $model->getFieldId();
        $field_id_name = $field_id->getName();

        # Ordenar

        $query = $model->order($query);

        $field_color_name = '';
        foreach ($model->getFields() as $f) {
            if ($f instanceof ColorField) {
                $field_color_name = $f->getName();
                break;
            }
        }

        if (!empty($value)) {

            foreach ((clone $query)->filter($field_id_name, $value)->select() as $row) {

                /** @var Model $row */
                $row->changeState(State::LIST_CHOICE);

                $pos = array_search($row->$field_id_name, $extras);

                if ($pos !== false) {
                    array_splice($extras, $pos, 1);
                }

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

            $query = $query->exclude($field_id_name, $value);

        }

        # Filtrar

        if (count($filters) > 0) {
            $query = $query->filter(QuerySql::_and($filters));
        }

        if ($enabled) {

            foreach ((clone $query)->limit($pre + 1)->select() as $row) {

                /** @var Model $row */
                $row->changeState(State::LIST_CHOICE);

                if (++$i > $pre) {
                    $extra = true;
                    break;
                }

                $pos = array_search($row->$field_id_name, $extras);

                if ($pos !== false) {
                    array_splice($extras, $pos, 1);
                }

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

        }

        if (count($extras) > 0) {

            foreach ((clone $query)->filter($field_id_name, $extras)->select() as $row) {

                /** @var Model $row */
                $row->changeState(State::LIST_CHOICE);

                $form_field->addChoice(
                    value: $row->$field_id_name,
                    label: (string) $row,
                    color: Color::tryFrom($row->$field_color_name ?? '')
                );

            }

        }

        $form_field->type = ($radio) ? 'radio' : 'choices';
        $form_field->multiple = $multiple;

        if (!is_null($typeahead)) {
            $form_field->type = 'typeahead';
            $form_field->typeahead = $typeahead;
        }

        elseif ($extra) {
            throw new FluxionException("Mais de '$pre' itens encontrados no campo '$name'! Informar typeahead para o campo");
        }

        return $form_field;

    }

}
