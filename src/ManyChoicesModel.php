<?php
namespace Fluxion;

use ReflectionProperty;
use ReflectionException;
use Fluxion\Database\Field\{ForeignKeyField, IntegerField, StringField};

class ManyChoicesModel extends Model
{

    use ModelMany;

    /** @throws Exception
     * @throws ReflectionException
     */
    public function __construct(protected ?Model  $model = null,
                                protected ?string $field = null)
    {

        if (!is_null($model)) {

            $reflection = new ReflectionProperty($this->model, $field);

            if (strval($reflection->getType()) != '?array') {
                throw new Exception("Campo $this->model:$field deve ser um array e permitir nulos!");
            }

            $this->comment = get_class($model) . " MN[$field]";

            $field_name = $this->field;

            $this->_table = $this->model->getTable();

            $this->_table->table .= '_has_' . $field_name;

            foreach (['a', 'b'] as $key) {

                if ($key == 'a') {
                    $this->_fields[$key] = new ForeignKeyField(get_class($this->model), real: true, type: 'CASCADE');
                }

                else {
                    $this->_fields[$key] = match ($this->model->getField($field_name)->getType()) {
                        'integer' => new IntegerField(primary_key: true),
                        default => new StringField(primary_key: true)
                    };
                }

                $this->_fields[$key]->column_name = $key;
                $this->_fields[$key]->required = true;
                $this->_fields[$key]->primary_key = true;
                $this->_fields[$key]->setName($key);
                $this->_fields[$key]->setModel($this);
                $this->_fields[$key]->setTypeProperty($reflection->getType());
                $this->_fields[$key]->initialize();

            }

            unset($this->a);
            unset($this->b);

        }

        parent::__construct();

    }

}
