<?php
namespace Fluxion\Connector;

use PDO;
use Throwable;
use Fluxion\Exception\{SqlFluxionException};
use Fluxion\{Color, Connector, Database, FluxionException, Model, Query, Time};
use Fluxion\Query\{QueryWhere};

class PostgreSQL extends Connector
{

    protected string $true_value = 'TRUE';
    protected string $false_value = 'FALSE';
    protected string $null_value = 'NULL';
    protected string $default_value = 'DEFAULT';
    protected string $utf_prefix = '';

    protected array $pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * @throws SqlFluxionException
     */
    protected function updateStructure(string $database): void
    {

        if (array_key_exists($database, $this->_structure)) return;

        # Buscando bancos de dados

        $sql = "SELECT datname AS name
                FROM pg_database
                WHERE datistemplate = false
                  AND datname = '$database'
                ORDER BY datname;";

        foreach ($this->fetch($sql) as $result) {

            $this->_structure[$result['name']] = [];

        }

        # Verificando se achou o resultado

        if (!array_key_exists($database, $this->_structure)) return;

        # Buscando esquemas (no banco atual da conexão)

        $sql = "SELECT nspname AS name, oid AS schema_id
                FROM pg_namespace
                WHERE nspname = 'public'
                   OR (nspname NOT IN ('information_schema', 'pg_catalog', 'pg_toast')
                       AND nspname NOT LIKE 'pg_temp_%'
                       AND nspname NOT LIKE 'pg_toast_temp_%')
                ORDER BY nspname;";

        foreach ($this->fetch($sql) as $result) {

            $this->_structure[$database][$result['name']] = [
                'id' => $result['schema_id'],
                'tables' => [],
            ];

        }

    }

    /**
     * @throws SqlFluxionException
     */
    protected function updateDatabase(Database\Table $table): void
    {

        $this->updateStructure($table->database);

        if (!isset($this->_structure[$table->database])) {

            $this->comment("Criando banco de dados '$table->database'", Color::GREEN, true);
            $this->execute("CREATE DATABASE \"$table->database\";");

            $this->_structure[$table->database] = [
                'public' => [
                    'id' => 1,
                    'tables' => [],
                ],
            ];

        }

        /*if ($this->_database != $table->database) {

            # No PostgreSQL não é possível trocar de banco na mesma sessão via 'USE database'
            $currentDb = $this->fetch("SELECT current_database() AS db")[0]['db'] ?? null;

            if ($currentDb && $currentDb !== $table->database) {
                $this->comment("<b>ALERTA</b>: PostgreSQL não suporta troca dinâmica de banco de dados na mesma conexão (atual: '$currentDb', desejado: '$table->database').", Color::ORANGE, break_after: true);
            }

            $this->_database = $table->database;

        }*/

        if (!isset($this->_structure[$table->database][$table->schema])) {

            $this->comment("Criando esquema '$table->schema'", Color::GREEN, true);
            $this->execute("CREATE SCHEMA IF NOT EXISTS \"$table->schema\";", true);

            $this->_structure[$table->database][$table->schema] = [];

        }

    }

    /**
     * @throws SqlFluxionException
     */
    public function getTableInfo(Database\Table $table): TableInfo
    {

        # Dados em branco

        $info = new TableInfo();

        # Buscando campos da tabela

        $sql = "SELECT a.attname AS column_name,
                       a.attnum AS column_id,
                       CASE
                           WHEN t.typname = 'int8' THEN 'bigint'
                           WHEN t.typname = 'int4' THEN 'integer'
                           WHEN t.typname = 'int2' THEN 'smallint'
                           WHEN t.typname = 'bool' THEN 'boolean'
                           WHEN t.typname = 'float8' THEN 'double precision'
                           WHEN t.typname = 'float4' THEN 'real'
                           WHEN t.typname = 'varchar' THEN 'varchar'
                           WHEN t.typname = 'bpchar' THEN 'char'
                           WHEN t.typname = 'text' THEN 'text'
                           WHEN t.typname = 'numeric' THEN 'numeric'
                           WHEN t.typname = 'timestamp' THEN 'timestamp'
                           WHEN t.typname = 'timestamptz' THEN 'timestamptz'
                           WHEN t.typname = 'date' THEN 'date'
                           WHEN t.typname = 'jsonb' THEN 'jsonb'
                           WHEN t.typname = 'json' THEN 'json'
                           ELSE t.typname
                       END AS type,
                       NOT a.attnotnull AS is_nullable,
                       (a.attidentity IN ('a', 'd')) AS is_identity,
                       CASE
                           WHEN t.typname IN ('varchar', 'bpchar') AND a.atttypmod > 4 THEN a.atttypmod - 4
                       END AS max_length,
                       CASE
                           WHEN t.typname = 'numeric' AND a.atttypmod > 4 THEN ((a.atttypmod - 4) >> 16) & 65535
                       END AS precision,
                       CASE
                           WHEN t.typname = 'numeric' AND a.atttypmod > 4 THEN (a.atttypmod - 4) & 65535
                       END AS scale,
                       pg_get_expr(ad.adbin, ad.adrelid) AS default_value
                FROM pg_attribute a
                         INNER JOIN pg_class c ON a.attrelid = c.oid
                         INNER JOIN pg_namespace n ON c.relnamespace = n.oid
                         INNER JOIN pg_type t ON a.atttypid = t.oid
                         LEFT JOIN pg_attrdef ad ON a.attrelid = ad.adrelid AND a.attnum = ad.adnum
                WHERE n.nspname = '$table->schema'
                  AND c.relname = '$table->table'
                  AND c.relkind IN ('r', 'p')
                  AND a.attnum > 0
                  AND NOT a.attisdropped
                ORDER BY a.attnum;";

        foreach ($this->fetch($sql) as $result) {

            $info->exists = true;

            $column = new TableColumn();

            $column->name = $result['column_name'];
            $column->id = $result['column_id'];
            $column->type = $result['type'];
            $column->nullable = (bool) $result['is_nullable'];
            $column->required = !$column->nullable;
            $column->identity = (bool) $result['is_identity'];
            $column->max_length = $result['max_length'];
            $column->precision = $result['precision'];
            $column->scale = $result['scale'];

            if ($column->identity) {
                $info->has_identity = true;
            }

            if (!is_null($result['default_value'])) {

                # Remove casts explícitos do PostgreSQL como 'texto'::character varying
                $default = preg_replace('/::[a-zA-Z0-9_ "]+$/', '', $result['default_value']);

                if (str_starts_with($default, '(') && str_ends_with($default, ')')) {
                    $default = substr($default, 1, -1);
                }

                $result['default_value'] = $default;

            }

            $column->default_value = $result['default_value'];
            $column->default_constraint = null;

            $info->columns[$result['column_name']] = $column;

        }

        # Retornando dados se não existir tabela

        if (!$info->exists) {
            return $info;
        }

        # Buscando chaves primárias da tabela

        $sql = "SELECT con.conname AS pk_name,
                       a.attname    AS column_name,
                       u.ord        AS column_order
                FROM pg_constraint con
                         INNER JOIN pg_class c ON con.conrelid = c.oid
                         INNER JOIN pg_namespace n ON c.relnamespace = n.oid
                         CROSS JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS u(attnum, ord)
                         INNER JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = u.attnum
                WHERE con.contype = 'p'
                  AND n.nspname = '$table->schema'
                  AND c.relname = '$table->table'
                ORDER BY u.ord;";

        foreach ($this->fetch($sql) as $result) {

            $info->primary_key_name = $result['pk_name'];
            $info->primary_keys[] = $result['column_name'];

        }

        # Buscando chaves estrangeiras da tabela

        $sql = "SELECT con.conname AS fk_name,
                       a_parent.attname AS parent_column,
                       n_ref.nspname AS referenced_schema,
                       c_ref.relname AS referenced_table,
                       a_ref.attname AS referenced_column,
                       CASE con.confdeltype
                           WHEN 'a' THEN 'NO_ACTION'
                           WHEN 'r' THEN 'RESTRICT'
                           WHEN 'c' THEN 'CASCADE'
                           WHEN 'n' THEN 'SET_NULL'
                           WHEN 'd' THEN 'SET_DEFAULT'
                       END AS delete_rule,
                       CASE con.confupdtype
                           WHEN 'a' THEN 'NO_ACTION'
                           WHEN 'r' THEN 'RESTRICT'
                           WHEN 'c' THEN 'CASCADE'
                           WHEN 'n' THEN 'SET_NULL'
                           WHEN 'd' THEN 'SET_DEFAULT'
                       END AS update_rule
                FROM pg_constraint con
                         INNER JOIN pg_class c_parent ON con.conrelid = c_parent.oid
                         INNER JOIN pg_namespace n_parent ON c_parent.relnamespace = n_parent.oid
                         INNER JOIN pg_class c_ref ON con.confrelid = c_ref.oid
                         INNER JOIN pg_namespace n_ref ON c_ref.relnamespace = n_ref.oid
                         CROSS JOIN LATERAL unnest(con.conkey, con.confkey) AS u(parent_attnum, ref_attnum)
                         INNER JOIN pg_attribute a_parent ON a_parent.attrelid = c_parent.oid AND a_parent.attnum = u.parent_attnum
                         INNER JOIN pg_attribute a_ref ON a_ref.attrelid = c_ref.oid AND a_ref.attnum = u.ref_attnum
                WHERE con.contype = 'f'
                  AND n_parent.nspname = '$table->schema'
                  AND c_parent.relname = '$table->table'
                ORDER BY con.conname;";

        foreach ($this->fetch($sql) as $result) {

            $foreign_key = new TableForeignKey();

            $foreign_key->name = $result['fk_name'];
            $foreign_key->parent_column = $result['parent_column'];
            $foreign_key->referenced_schema = $result['referenced_schema'];
            $foreign_key->referenced_table = $result['referenced_table'];
            $foreign_key->referenced_column = $result['referenced_column'];
            $foreign_key->delete_rule = $result['delete_rule'];
            $foreign_key->update_rule = $result['update_rule'];

            $info->foreign_keys[$result['fk_name']] = $foreign_key;

        }

        # Buscando índices da tabela

        $sql = "SELECT i.relname AS name,
                       am.amname AS type_desc,
                       ix.indisunique AS is_unique,
                       a.attname AS column_name,
                       (u.ord > ix.indnkeyatts) AS is_included_column,
                       u.ord AS key_ordinal
                FROM pg_index ix
                         INNER JOIN pg_class t ON t.oid = ix.indrelid
                         INNER JOIN pg_namespace n ON n.oid = t.relnamespace
                         INNER JOIN pg_class i ON i.oid = ix.indexrelid
                         INNER JOIN pg_am am ON i.relam = am.oid
                         CROSS JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS u(attnum, ord)
                         INNER JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = u.attnum
                WHERE n.nspname = '$table->schema'
                  AND t.relname = '$table->table'
                  AND NOT ix.indisprimary
                ORDER BY i.relname, u.ord;";

        foreach ($this->fetch($sql) as $result) {

            if (!isset($info->indexes[$result['name']])) {

                $index = new TableIndex();

                $index->name = $result['name'];
                $index->type = strtoupper($result['type_desc']);
                $index->unique = (bool) $result['is_unique'];

                $info->indexes[$result['name']] = $index;

            }

            if ($result['is_included_column']) {
                $info->indexes[$result['name']]->includes[] = $result['column_name'];
            }

            else {
                $info->indexes[$result['name']]->columns[] = $result['column_name'];
            }

        }

        # Retornando dados

        return $info;

    }

    /** @throws FluxionException */
    protected function getDatabaseType(Database\Field $value): string
    {

        return match ($value->getType()) {
            'json' => 'jsonb',
            'link', 'color' => 'varchar',
            'text', 'iframe', 'map', 'html', 'upload', 'image', 'string', 'password' => ($value->max_length) ? 'varchar' : 'text',
            'integer' => 'bigint',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'timestamp',
            'float' => 'double precision',
            'decimal', 'numeric' => 'numeric',
            'geography' => 'geography',
            default => throw new FluxionException("Tipo {$value->getType()} não implementado"),
        };

    }

    /** @throws FluxionException */
    protected function getColumnCommand(Database\Field $value): string
    {

        # Definição do tipo

        $type = $this->getDatabaseType($value);

        # Criação do comando

        $command = "$type";

        # Complementos

        if ($type === 'varchar' && $value->max_length) {
            $command .= "($value->max_length)";
        }

        elseif ($type === 'numeric') {
            $command .= "($value->precision,$value->scale)";
        }

        # Valores nulos

        if ($value->required || $value->identity) {
            $command .= ' NOT NULL';
        }

        # Auto incremento (BY DEFAULT permite inserção explícita de IDs como no IDENTITY_INSERT)

        if ($value->identity) {
            $command .= ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return $command;

    }

    protected function getDefaultCommand(Database\Field $value): ?string
    {

        # Valor padrão

        if (!is_null($value->default)) {

            $default = $value->default;

            if (in_array(strtolower($default), ['getdate()', 'now()', 'current_timestamp'])) {
                return 'CURRENT_TIMESTAMP';
            }

            if (!$value->default_literal) {
                $default = $this->escape($value->default);
            }

            return "$default";

        }

        return null;

    }

    /**
     * @throws FluxionException
     * @throws Throwable
     */
    protected function executeSync(Model $model): void
    {

        $table = $model->getTable();
        $fields = $model->getFields();

        $this->updateDatabase($table);

        $prefix = strtolower("{$table->schema}_$table->table");

        $info = $this->getTableInfo($table);

        # Buscando as chaves necessárias

        $primary_keys = [];
        $foreign_keys = [];

        foreach ($fields as $key => $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            # Chave primária

            if ($f->isPrimaryKey()) {
                $primary_keys[] = $f->column_name;
            }

            # Chave estrangeira

            if ($f->isForeignKey()) {
                $foreign_keys[$key] = $f;
            }

        }

        # Quando a tabela já existir

        if ($info->exists) {

            $this->comment("Tabela '$table->schema.$table->table' já existe");

            # Campos utilizados

            foreach ($fields as $key => $value) {

                if ($value->fake || $value->assistant_table) {
                    continue;
                }

                $default_value = $this->getDefaultCommand($value);
                $target_type = $this->getDatabaseType($value);

                # Campo ainda não existe

                if (!isset($info->columns[$key])) {

                    $this->comment("Incluindo campo '$key'", Color::GREEN, true);

                    $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                        . "\tADD COLUMN \"$value->column_name\" {$this->getColumnCommand($value)}";

                    if (!is_null($default_value)) {
                        $sql .= " DEFAULT $default_value";
                    }

                    $sql .= ";";

                    $info->columns[$key] = new TableColumn();
                    $info->columns[$key]->type = $target_type;
                    $info->columns[$key]->required = $value->required;
                    $info->columns[$key]->extra = false;
                    $info->columns[$key]->default_value = $default_value;

                    $this->execute($sql, true);

                }

                # Campo existe e precisa ser alterado

                elseif ($info->columns[$key]->type != $target_type
                    || $info->columns[$key]->max_length != $value->max_length
                    || $info->columns[$key]->required != $value->required) {

                    $info->columns[$key]->extra = false;

                    $this->comment("Alterando campo '$key: {$info->columns[$key]}'", Color::BROWN, true);

                    $alter_actions = [];

                    if ($info->columns[$key]->type != $target_type || $info->columns[$key]->max_length != $value->max_length) {

                        $type_def = $target_type;

                        if ($target_type === 'varchar' && $value->max_length) {
                            $type_def .= "($value->max_length)";
                        } elseif ($target_type === 'numeric') {
                            $type_def .= "($value->precision,$value->scale)";
                        }

                        $alter_actions[] = "\tALTER COLUMN \"$value->column_name\" TYPE $type_def USING \"$value->column_name\"::$type_def";
                    }

                    if ($info->columns[$key]->required != $value->required) {
                        $null_action = ($value->required || $value->identity) ? 'SET NOT NULL' : 'DROP NOT NULL';
                        $alter_actions[] = "\tALTER COLUMN \"$value->column_name\" $null_action";
                    }

                    if (count($alter_actions) > 0) {
                        $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                            . implode(",\n", $alter_actions) . ";";

                        $this->execute($sql, true);
                    }

                }

                # Campo já existe
                else {

                    $info->columns[$key]->extra = false;

                    $this->comment("Campo '$key: {$info->columns[$key]}' já existe");

                }

                # Valor padrão do campo

                if (isset($info->columns[$key])
                    && !is_null($info->columns[$key]->default_value)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Excluindo valor padrão '{$info->columns[$key]->default_value}' no campo '$key'", Color::RED, true);

                    $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                        . "\tALTER COLUMN \"$value->column_name\" DROP DEFAULT;";

                    $this->execute($sql, true);

                }

                if (!is_null($value->default)
                    && $info->columns[$key]->default_value != $default_value) {

                    $this->comment("Incluindo valor padrão '$default_value' no campo '$key'", Color::BROWN, true);

                    $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                        . "\tALTER COLUMN \"$value->column_name\" SET DEFAULT $default_value;";

                    $this->execute($sql, true);

                }

            }

            # Campos não utilizados

            foreach ($info->columns as $key => $value) {

                if (!$value->extra) {
                    continue;
                }

                $this->comment("Campo '$key: $value' não utilizado", Color::DEEP_ORANGE);

            }

            # Chaves primárias

            if (count($primary_keys) > 0) {

                # Verificar se existe chave com os mesmos campos

                if ($info->primary_keys == $primary_keys) {

                    $detail = implode(", ", $primary_keys);

                    $this->comment("Chave primária '$info->primary_key_name ($detail)' já existe");

                }

                else {

                    if (!is_null($info->primary_key_name)) {

                        $this->comment("Apagando chave primária '$info->primary_key_name'", Color::RED, true);

                        $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                            . "\tDROP CONSTRAINT \"$info->primary_key_name\";";

                        $this->execute($sql, true);

                    }

                    $uid = bin2hex(random_bytes(10));

                    $primary_key_name = "{$prefix}_pk_$uid";
                    $primary_key = implode('", "', $primary_keys);

                    $this->comment("Criando chave primária '$primary_key_name'", Color::GREEN, true);

                    $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                        . "\tADD CONSTRAINT \"$primary_key_name\" PRIMARY KEY (\"$primary_key\");";

                    $this->execute($sql, true);

                }

            }

            # Chave primária não utilizada

            else if (!is_null($info->primary_key_name)) {

                $this->comment("Chave primária '$info->primary_key_name' não utilizada", Color::DEEP_ORANGE);

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\Field\ForeignKeyField $foreign_key */
                foreach ($foreign_keys as $key => $foreign_key) {

                    $uid = bin2hex(random_bytes(10));

                    $foreign_key_name = "{$prefix}_fk_$uid";

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    $field = $fields[$key];

                    $foreign_key_exists = false;

                    $reference_field = $foreign_key->getField();

                    $foreign_key_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                    if (!is_null($foreign_key->type)) {
                        $foreign_key_type = $foreign_key->type;
                    }

                    foreach ($info->foreign_keys as $fk_key => $fk_value) {

                        if ($fk_value->parent_column != $field->column_name) {
                            continue;
                        }

                        $info->foreign_keys[$fk_key]->extra = false;

                        if ($fk_value->referenced_schema != $reference->schema
                            || $fk_value->referenced_table != $reference->table
                            || $fk_value->referenced_column != $reference_field->column_name
                            || $fk_value->delete_rule != $foreign_key_type
                            || $fk_value->update_rule != $foreign_key_type) {

                            $detail = "($key) → $fk_value->referenced_table ($fk_value->referenced_column)";

                            $this->comment("Apagando chave estrangeira '$fk_key' $detail", Color::RED, true);

                            $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                                . "\tDROP CONSTRAINT \"$fk_key\";";

                            $this->execute($sql, true);

                        }

                        else {

                            $foreign_key_exists = true;

                            $detail = "($key) → $reference->table ($reference_field->column_name)";

                            $this->comment("Chave estrangeira '$fk_key $detail' já existe");

                        }

                    }

                    if (!$foreign_key_exists) {

                        $this->comment("Criando chave estrangeira '$foreign_key_name'", Color::GREEN, true);

                        $foreign_key_type = str_replace('_', ' ', $foreign_key_type);

                        if ($reference->database != $table->database) {
                            $this->comment("<b>ALERTA</b>: Não é possível a inclusão de chave estrangeira de outro banco de dados (campo '$key').", Color::ORANGE, break_after: true);
                        }

                        else {

                            $sql = "ALTER TABLE \"$table->schema\".\"$table->table\"\n"
                                . "\tADD CONSTRAINT \"$foreign_key_name\" "
                                . "FOREIGN KEY (\"$field->column_name\") "
                                . "REFERENCES \"$reference->schema\".\"$reference->table\" (\"$reference_field->column_name\") "
                                . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type;";

                            $this->execute($sql, true);

                        }

                    }

                }

            }

            # Chaves estrangeiras não utilizadas

            foreach ($info->foreign_keys as $key => $foreign_key) {

                if (!$foreign_key->extra) {
                    continue;
                }

                $this->comment("Chave estrangeira '$key' não utilizada", Color::DEEP_ORANGE);

            }

        }

        # Criar a tabela se não existir

        else {

            $create_fields = [];

            # Campos utilizados

            foreach ($fields as $value) {

                if ($value->fake || $value->assistant_table) {
                    continue;
                }

                $command = "\t\"$value->column_name\" {$this->getColumnCommand($value)}";

                $default_value = $this->getDefaultCommand($value);

                if (!is_null($default_value)) {
                    $command .= " DEFAULT $default_value";
                }

                $create_fields[] = $command;

            }

            # Campos da tabela

            $command = implode(",\n", $create_fields);

            # Chaves primárias

            if (count($primary_keys) > 0) {

                $primary_key = implode('", "', $primary_keys);

                $uid = bin2hex(random_bytes(10));

                $command .= ",\n\tCONSTRAINT \"{$prefix}_pk_$uid\" PRIMARY KEY (\"$primary_key\")";

            }

            # Chaves estrangeiras

            if (count($foreign_keys) > 0) {

                /** @var Database\Field\ForeignKeyField $foreign_key */
                foreach ($foreign_keys as $key => $foreign_key) {

                    if (!$foreign_key->real) {
                        continue;
                    }

                    $reference = $foreign_key->getReferenceModel()->getTable();

                    if ($reference->database != $table->database) {
                        $this->comment("<b>ALERTA</b>: Não é possível a inclusão de chave estrangeira de outro banco de dados (campo '$key').", Color::ORANGE, break_after: true);
                    }

                    else {

                        $field = $fields[$key];

                        $reference_field = $foreign_key->getField();

                        $foreign_key_type = ($field->required) ? 'NO_ACTION' : 'SET_NULL';

                        if (!is_null($foreign_key->type)) {
                            $foreign_key_type = $foreign_key->type;
                        }

                        $foreign_key_type = str_replace('_', ' ', $foreign_key_type);

                        $uid = bin2hex(random_bytes(10));

                        $command .= ",\n\tCONSTRAINT \"{$prefix}_fk_$uid\" "
                            . "FOREIGN KEY (\"$field->column_name\") "
                            . "REFERENCES \"$reference->schema\".\"$reference->table\" (\"$reference_field->column_name\") "
                            . "ON UPDATE $foreign_key_type ON DELETE $foreign_key_type";

                    }

                }

            }

            # Comandos

            $sql = "CREATE TABLE \"$table->schema\".\"$table->table\" (\n$command\n);";

            $this->comment("Criando tabela '$table->schema.$table->table'", Color::GREEN, true);
            $this->execute($sql, true);

            $comment = str_replace("'", "''", $model->getComment());

            $this->comment("Adicionando descrição na tabela '$table->schema.$table->table'", Color::GREEN, true);
            $sql = "COMMENT ON TABLE \"$table->schema\".\"$table->table\" IS '$comment (" . Time::NOW->value() . ")';";

            $this->execute($sql, true);

        }

        # Índices

        foreach ($model->getIndexes() as $create_index) {

            $index_exists = false;

            foreach ($info->indexes as $key => $index) {

                $a = $index->columns;
                $b = $create_index->columns;

                sort($a);
                sort($b);

                if ($index->unique == $create_index->unique
                    && $a == $b
                    && $index->includes == $create_index->includes) {

                    $index_exists = true;

                    $info->indexes[$key]->extra = false;

                    if ($index->columns == $create_index->columns) {
                        $this->comment("Índice '$key $index' já existe");
                    }

                    else {
                        $this->comment("Índice '$key $index' já existe com outra ordem", Color::PURPLE);
                    }

                }

            }

            if (!$index_exists) {

                $index_name = $prefix . '_index_' . bin2hex(random_bytes(10));

                $this->comment("Criando índice '$index_name'", Color::GREEN, true);

                if ($create_index->unique) {
                    $sql = "CREATE UNIQUE INDEX \"$index_name\" ON \"$table->schema\".\"$table->table\"";
                }

                else {
                    $sql = "CREATE INDEX \"$index_name\" ON \"$table->schema\".\"$table->table\"";
                }

                $sql .= ' ("' . implode('", "', $create_index->columns) . '")';

                if (count($create_index->includes) > 0) {
                    $sql .= ' INCLUDE ("' . implode('", "', $create_index->includes) . '")';
                }

                $sql .= ';';

                $this->execute($sql, true);

            }

        }

        # Índices não utilizados

        foreach ($info->indexes as $key => $index) {

            if (!$index->extra) {
                continue;
            }

            $this->comment("Índice '$key $index' não utilizado", Color::DEEP_ORANGE);

        }

        if (!is_null($this->log_stream)) {
            $this->log_stream->write("\n");
        }

    }

    /**
     * @throws FluxionException
     */
    public function filter(QueryWhere $filter, Query $query, ?string $id): string
    {

        $model = $query->getModel();

        $txt_id = (!is_null($id)) ? "$id." : '';

        $not = ($filter->not) ? ' NOT ' : '';

        if (is_object($filter->field)) {

            $where = [];
            /** @var QueryWhere $w */
            foreach ($filter->field->_filters as $w) {
                if (!is_null($w)) {
                    $where[] = $this->filter($w, $query, $id);
                }
            }

            $where = implode(" {$filter->field->_type} ", $where);

            return "$not($where)";

        }

        $_field = explode('__', $filter->field);

        $field_obj = $model->getField($_field[0]);

        $field = $field_obj->column_name;

        $type = '=';

        for ($i = 1; $i <= count($_field); $i++) {

            if (isset($_field[$i])) {

                if ($_field[$i] == 'json') {

                    $json_name = $_field[$i + 1]
                        ?? throw new FluxionException("Utilizar padrão 'campo__json__variavel'");

                    $field = "({$txt_id}\"$field\"::jsonb ->> '$json_name')";

                    $filter->value = (string) $filter->value;

                    $i++;

                    continue;

                }

                $type = match ($_field[$i]) {
                    'ne' => '<>',
                    'lt' => '<',
                    'gt' => '>',
                    'lte' => '<=',
                    'gte' => '>=',
                    'like' => 'ILIKE',
                    default => '=',
                };

                $field = match ($_field[$i]) {
                    'second', 'minute', 'hour', 'day', 'week', 'month', 'year' => "EXTRACT(" . strtoupper($_field[$i]) . " FROM {$txt_id}\"$field\")",
                    'weekday', 'dow' => "(EXTRACT(DOW FROM {$txt_id}\"$field\") + 1)",
                    'date' => "CAST({$txt_id}\"$field\" AS DATE)",
                    'length' => "LENGTH({$txt_id}\"$field\")",
                    'only_number', 'clean' => "REGEXP_REPLACE({$txt_id}\"$field\", '[^0-9]', '', 'g')",
                    default => $field,
                };

            }

        }

        if ($field == $field_obj->column_name) {
            $field = "{$txt_id}\"$field\"";
        }

        if (is_null($filter->value)) {
            return "$field IS {$not}NULL";
        }

        if (is_string($filter->value)
            || is_numeric($filter->value)
            || is_bool($filter->value)) {

            $escaped_value = $filter->value;

            if ($type == 'ILIKE') {
                $escaped_value = str_replace(['_', '%'], '', $escaped_value);
            }

            if (in_array($field_obj->getType(), ['integer', 'float', 'decimal'])
                && $escaped_value
                && !is_numeric($escaped_value)
                && !is_bool($escaped_value)) {
                throw new FluxionException("O valor <b>$filter->value</b> não é numérico!");
            }

            if ($type == 'ILIKE') {
                return "$field::text {$not}ILIKE {$this->escape($filter->value)}";
            }

            return "$not$field $type " . $this->escape($filter->value);

        }

        if (is_array($filter->value)) {

            if (count($filter->value) > 0) {

                if (in_array(null, $filter->value)) {
                    return "($field$not IN " . $this->escape($filter->value) . " OR $field IS {$not}NULL)";
                }

                return "$field$not IN " . $this->escape($filter->value);

            }

            else {
                return '1=0';
            }

        }

        if (is_object($filter->value)) {

            if (get_class($filter->value) == Query::class) {
                return "$field$not IN (" . self::sql_select($filter->value, true) . ")";
            }

        }

        return false;

    }

    /**
     * @throws FluxionException
     */
    public function sql_select(Query $query, bool $inline = false): string
    {

        $id = $this->getTableId();
        $model = $query->getModel();
        $table = $model->getTable();

        $fields = [];

        if ($query->isAllFields()) {

            foreach ($model->getFields() as $field) {

                if (!$field->fake && !$field->assistant_table) {

                    if ($field instanceof Database\Field\GeographyField) {
                        $fields[] = "ST_AsText($id.\"$field->column_name\") AS \"$field->column_name\"";
                    }

                    else {
                        $fields[] = "$id.\"$field->column_name\"";
                    }

                }

            }

        }

        foreach ($query->getFields() as $query_field) {

            $field = $model->getField($query_field->field);

            if (is_null($query_field->aggregator)) {
                $fields[] = "$id.\"$field->column_name\"";
            }

            elseif ($query_field->aggregator == 'COUNT') {

                if (is_null($field)) {
                    $fields[] = "COUNT(*) AS \"$query_field->name\"";
                }

                else {
                    $fields[] = "COUNT($id.\"$field->column_name\") AS \"$query_field->name\"";
                }

            }

            else {
                $fields[] = "$query_field->aggregator($id.\"$field->column_name\") AS \"$query_field->name\"";
            }

        }

        $where = [];

        foreach ($query->getWhere() as $w) {
            $where[] = $this->filter($w, $query, $id);
        }

        $order_by = [];

        foreach ($query->getOrderBy() as $o) {
            $field = $model->getField($o->field);
            $order_by[] = "$id.\"$field->column_name\" $o->order";
        }

        $group_by = [];

        foreach ($query->getGroupBy() as $g) {
            $field = $model->getField($g->field);
            $group_by[] = "$id.\"$field->column_name\"";
        }

        $limit_offset = null;

        if (!is_null($l = $query->getLimit())) {

            $limit_offset = "LIMIT $l->limit";

            if ($l->offset > 0) {
                $limit_offset .= " OFFSET $l->offset";
            }

        }

        $sql = "SELECT\t" . implode(",\n\t", $fields);

        $sql .= "\nFROM \"$table->schema\".\"$table->table\" AS $id";

        if (count($where) > 0) {
            $sql .= "\nWHERE\t" . implode(" AND\n\t", $where);
        }

        if (count($group_by) > 0) {
            $sql .= "\nGROUP BY " . implode(",\n\t", $group_by);
        }

        if (count($order_by) > 0) {
            $sql .= "\nORDER BY " . implode(",\n\t", $order_by);
        }

        if (!is_null($limit_offset)) {
            $sql .= "\n$limit_offset";
        }

        if ($inline) {
            $sql = str_replace(["\n\t", "\n", "\t"], " ", $sql);
        }

        return $sql;

    }

    /**
     * @throws FluxionException
     */
    public function sql_insert(Model $model, array $data = []): string
    {

        $fields = $model->getFields();
        $primary_keys = $model->getPrimaryKeys();
        $table = $model->getTable();

        $fields_sql = [];

        $inserts_sql = [];
        $returning_sql = [];

        $identity_insert = false;

        # Inclusão de um único registro

        if (count($data) == 0) {

            # Valores a serem devolvidos

            foreach ($primary_keys as $p) {
                $returning_sql[] = "\"$p->column_name\"";
            }

            $values_sql = [];

            foreach ($fields as $key => $f) {

                if ($f->fake || $f->assistant_table) {
                    continue;
                }

                if (!$f->isChanged() && $model->isSaved()) {
                    continue;
                }

                $value = $f->getValue(true);

                if (!is_null($f->default) && !in_array("\"$f->column_name\"", $returning_sql)) {
                    $returning_sql[] = "\"$f->column_name\"";
                }

                if ($f->identity && is_null($f->getValue())) {
                    continue;
                }

                if ($f->required && is_null($value) && is_null($f->default)) {
                    throw new FluxionException("Campo '$key' não pode ser nulo");
                }

                if ($f->identity) {
                    $identity_insert = true;
                }

                if ($f instanceof Database\Field\GeographyField && !is_null($value)) {
                    $values_sql[] = "ST_GeogFromText('SRID=4674;$value')";
                }

                elseif ($f->required && is_null($value) && !is_null($f->default)) {
                    $values_sql[] = $this->default_value;
                }

                else {
                    $values_sql[] = $this->escape($value);
                }

            }

            $inserts_sql[] = "(" . implode(", ", $values_sql) . ")";

        }

        # Inclusão de vários registros

        else {

            foreach ($data as $d) {

                $values_sql = [];

                $i_model = clone $model;

                foreach ($fields as $key => $f) {

                    if (!array_key_exists($key, $d)) {
                        $f->clear();
                        continue;
                    }

                    $value = $d[$key] ?? null;

                    if ($f->identity && !is_null($value)) {
                        $identity_insert = true;
                    }

                    $i_model->$key = $value;

                }

                if ($i_model->onSave()) {

                    foreach ($fields as $key => $f) {

                        if ($f->fake || $f->assistant_table) {
                            continue;
                        }

                        if ($f->identity && is_null($i_model->$key)) {
                            continue;
                        }

                        if ($f instanceof Database\Field\GeographyField && !is_null($i_model->$key)) {
                            $values_sql[] = "ST_GeogFromText('SRID=4326;{$i_model->$key}')";
                        }

                        elseif ($f->required && is_null($i_model->$key) && !is_null($f->default)) {
                            $values_sql[] = $this->default_value;
                        }

                        else {
                            $values_sql[] = $this->escape($i_model->$key);
                        }

                    }

                    $inserts_sql[] = "(" . implode(", ", $values_sql) . ")";

                }

                else {
                    $this->comment('Erro ao salvar o registro: ' . json_encode($d), Color::RED);
                }

            }

        }

        # Campos

        foreach ($fields as $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            if (!$f->isChanged() && $model->isSaved()) {
                continue;
            }

            if ($f->identity && !$identity_insert) {
                continue;
            }

            $fields_sql[] = "\"$f->column_name\"";

        }

        if (count($fields_sql) == 0) {
            throw new FluxionException("Nenhum campo alterado para incluir");
        }

        if (count($inserts_sql) == 0) {
            throw new FluxionException("Nenhum registro para incluir");
        }

        $sql = "INSERT INTO \"$table->schema\".\"$table->table\"";

        $sql .= " (\n\t" . implode(",\n\t", $fields_sql) . "\n)";

        $sql .= "\nVALUES\t" . implode(",\n\t", $inserts_sql);

        if (count($returning_sql) > 0) {
            $sql .= "\nRETURNING\t" . implode(",\n\t", $returning_sql);
        }

        $sql .= ";";

        # Sincroniza a sequence caso tenham sido inseridos IDs manualmente
        /*if ($identity_insert) {

            foreach ($primary_keys as $p) {
                if ($p->identity) {
                    $sql .= "\nSELECT setval(pg_get_serial_sequence('\"$table->schema\".\"$table->table\"', '$p->column_name'), COALESCE((SELECT MAX(\"$p->column_name\") FROM \"$table->schema\".\"$table->table\"), 1), true);";
                }
            }

        }*/

        return $sql;

    }

    /**
     * @throws FluxionException
     */
    public function sql_update(Model $model, Query $query): ?string
    {

        $table = $model->getTable();

        $where = [];

        foreach ($query->getWhere() as $w) {
            $where[] = $this->filter($w, $query, null);
        }

        $update = [];

        foreach ($model->getFields() as $f) {

            if ($f->fake || $f->assistant_table) {
                continue;
            }

            $f->update();

            if ($f instanceof Database\Field\GeographyField && !is_null($f->getValue())) {
                $update[] = "\"$f->column_name\" = ST_GeogFromText('SRID=4326;{$f->getValue()}')";
            }

            elseif ($f->isChanged()) {
                $update[] = "\"$f->column_name\" = " . $this->escape($f->getValue());
            }

        }

        if (count($update) == 0) {
            return null;
        }

        $sql = "UPDATE \"$table->schema\".\"$table->table\"";

        $sql .= "\nSET\t" . implode(",\n\t", $update);

        if (count($where) > 0) {
            $sql .= "\nWHERE\t" . implode(" AND\n\t", $where);
        }

        return "$sql;";

    }

}