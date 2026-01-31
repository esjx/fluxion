<?php
namespace Fluxion\Database;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{

    public function __construct(public string $table,
                                public ?string $schema = null,
                                public ?string $database = null,
                                public ?bool $view = false)
    {

        if (is_null($database)
            && preg_match('/database=(?P<database>[A-Za-z0-9_]+)/si', $_ENV['DB_HOST'] ?? '', $data)) {

            $this->database = $data['database'];

        }

        if (preg_match('/^(?P<database>[A-Za-z0-9_]+)\.(?P<schema>[A-Za-z0-9_]+)\.(?P<table>[A-Za-z0-9_]+)$/si', $this->table, $data)) {

            $this->database = $data['database'];
            $this->schema = $data['schema'];
            $this->table = $data['table'];

        }

        elseif (preg_match('/^(?P<schema>[A-Za-z0-9_]+)\.(?P<table>[A-Za-z0-9_]+)$/si', $this->table, $data)) {

            $this->schema = $data['schema'];
            $this->table = $data['table'];

        }

    }

}
