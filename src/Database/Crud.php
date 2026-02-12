<?php
namespace Fluxion\Database;

use Attribute;
use BackedEnum;
use Fluxion\{Exception, Model};

#[Attribute(Attribute::TARGET_CLASS)]
class Crud
{

    public ?string $subtitle;
    public string $form_size;

    public function __construct(public string          $title,
                                public ?string         $plural_title = null,
                                null|string|BackedEnum $subtitle = null,
                                public string          $description = 'Utilize os filtros ou a opção de busca para algum item específico',
                                public string          $not_found_message = 'Nenhum registro encontrado',
                                public string          $search_placeholder = 'Buscar...',
                                public string          $update_title = 'Atualizado em',
                                public string          $update_format = 'dd/MM/y HH:mm',
                                public ?string         $field_tab = null,
                                FormSize               $form_size = FormSize::MEDIUM,
                                public int             $items_per_page = 20,
                                public int             $refresh_time = 60000000)
    {

        $this->form_size = $form_size->value;

        if ($subtitle instanceof BackedEnum) {
            $this->subtitle = $subtitle->value;
        }

        else {
            $this->subtitle = $subtitle;
        }

        if (empty($this->plural_title)) {
            $this->plural_title = $this->title . 's';
        }

    }

    /**
     * @throws Exception
     */
    public function initialize(Model $model): void
    {

        if (!is_null($this->field_tab) && !property_exists($model, $this->field_tab)) {
            throw new Exception("Campo '$model:$this->field_tab' não existe!");
        }

    }

}
