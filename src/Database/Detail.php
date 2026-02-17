<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\{Exception, Icon, Mask};

#[Attribute(Attribute::TARGET_PROPERTY)]
class Detail
{

    protected string $_name;
    public ?string $mask = null;
    public string $placeholder = '';
    public ?string $pattern = null;

    function setName(string $name): void
    {

        $this->_name = $name;

        if (empty($this->label)) {
            $this->label = ucfirst($this->_name);
        }

    }

    /**
     * @param string|null $label
     * @param string|null $mask_class
     * @param bool $searchable
     * @param bool $filterable
     * @param Icon|null $filter_icon
     * @param string|null $typeahead
     * @param string|null $help
     * @param int|null $max_length
     * @param int|null $size
     * @param Condition[]|null $visible_conditions
     * @param Condition[]|null $required_conditions
     * @param Condition[]|null $choices_conditions
     * @param Condition[]|null $enabled_conditions
     * @param Condition[]|null $label_conditions
     * @param Condition[]|null $help_conditions
     * @throws Exception
     */
    public function __construct(public ?string $label = null,
                                public ?string $mask_class = null,
                                public bool    $searchable = false,
                                public bool    $filterable = false,
                                public ?Icon   $filter_icon = null,
                                public ?string $typeahead = null,
                                public ?string $help = null,
                                public ?int    $max_length = null,
                                public ?int    $size = 12,
                                public ?array  $visible_conditions = null,
                                public ?array  $required_conditions = null,
                                public ?array  $choices_conditions = null,
                                public ?array  $enabled_conditions = null,
                                public ?array  $label_conditions = null,
                                public ?array  $help_conditions = null)
    {
        $this->initialize();
    }

    /**
     * @throws Exception
     */
    public function initialize(): void
    {

        if (!in_array($this->size, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            throw new Exception(message: "Tamanho do campo inválido: '$this->size'");
        }

        if (!is_null($this->mask_class)) {

            if (!class_exists($this->mask_class)) {
                throw new Exception(message: "Mascára '$this->mask_class' não encontrada");
            }

            $mask = new $this->mask_class;

            if (!is_subclass_of($mask, Mask::class)) {
                throw new Exception(message: "Classe '$this->mask_class' não herda 'Mask'");
            }

            $this->mask = $mask->mask;
            $this->placeholder = $mask->placeholder ?? '';
            $this->pattern = $mask->pattern_validator;
            $this->label = $this->label ?? $mask->label;
            $this->max_length = mb_strlen($mask->mask);

        }

    }

}
