<?php
namespace Fluxion\Connector;

class TableForeignKey
{

    public string $name;
    public string $parent_column;
    public string $referenced_schema;
    public string $referenced_table;
    public string $referenced_column;
    public string $delete_rule;
    public string $update_rule;
    public bool $extra = true;

}
