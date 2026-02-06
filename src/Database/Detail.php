<?php
namespace Fluxion\Database;

use Attribute;
use Fluxion\CustomException;
use Fluxion\Mask\Mask;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Detail
{

    protected string $_name;
    protected ?string $mask = null;
    protected ?string $placeholder = null;
    protected ?string $pattern = null;

    function setName(string $name): void
    {

        $this->_name = $name;

        if (empty($this->label)) {
            $this->label = ucfirst($this->_name);
        }

    }

    public function __construct(public ?string $label = null,
                                public ?string $mask_class = null,
                                public bool    $searchable = false,
                                public bool    $filterable = false,
                                public ?string $typeahead = null,
                                public ?int    $max_length = null,
                                public ?int    $size = 12
    )
    {

    }

    /**
     * @throws CustomException
     */
    public function initialize(): void
    {

        if (!in_array($this->size, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            throw new CustomException(message: "Tamanho do campo '$this->_name' inválido: '$this->size'", log: false);
        }

        if (!is_null($this->mask_class)) {

            if (!class_exists($this->mask_class)) {
                throw new CustomException(message: "Mascára '$this->mask_class' não encontrada", log: false);
            }

            $mask = new $this->mask_class;

            if (!is_subclass_of($mask, Mask::class)) {
                throw new CustomException(message: "Classe '$this->mask_class' não herda 'Mask'", log: false);
            }

            $this->mask = $mask->mask;
            $this->placeholder = $mask->placeholder;
            $this->pattern = $mask->pattern_validator;
            $this->label = $this->label ?? $mask->label;
            $this->max_length = $this->max_length ?? $mask->max_length;

        }

    }

}
