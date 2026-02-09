<?php
namespace Fluxion\Connector;

class TableInfo
{

    public bool $exists = false;

    public bool $has_identity = false;

    /** @var array<string, TableColumn> */
    public array $columns = [];

    /** @var array<string> */
    public array $primary_keys = [];

    public ?string $primary_key_name = null;

    /** @var array<string, TableForeignKey> */
    public array $foreign_keys = [];

    /** @var array<string, TableIndex> */
    public array $indexes = [];

}
