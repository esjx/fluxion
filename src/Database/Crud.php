<?php
namespace Fluxion\Database;

use Attribute;
use BackedEnum;

#[Attribute(Attribute::TARGET_CLASS)]
class Crud
{

    public ?string $subtitle;

    public function __construct(public ?string $title = null,
                                null|string|BackedEnum $subtitle = null,
                                public string $description = 'Utilize os filtros ou a opção de busca para algum item específico',
                                public string $not_found_message = 'Nenhum registro encontrado',
                                public string $search_placeholder = 'Buscar...',
                                public string $update_title = 'Criado em',
                                public string $update_format = 'dd/MM/y HH:mm',
                                public ?string $field_tab = null,
                                public int $itens_per_page = 20,
                                public int $refresh_time = 60000)
    {

        if ($subtitle instanceof BackedEnum) {
            $this->subtitle = $subtitle->value;
        }

        else {
            $this->subtitle = $subtitle;
        }

    }

}
