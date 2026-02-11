<?php
namespace Fluxion;

use ReflectionException;
use ReflectionProperty;
use Fluxion\Database\Field\{ForeignKeyField};

class ManyToManyModel extends Model
{

    use ModelMany;

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function __construct(protected ?Model  $model = null,
                                protected ?string $field = null,
                                protected bool    $inverted = false)
    {

        if (!is_null($model)) {

            $reflection = new ReflectionProperty($this->model, $field);

            if (strval($reflection->getType()) != '?array') {
                throw new Exception("Campo $this->model:$field deve ser um array e permitir nulos!");
            }

            $this->comment = get_class($model) . " MN[$field]";

            /** @var array<string, Model> $models */
            $models = [];

            $fields = $this->model->getFields();

            # Uso normal

            if (!$this->inverted) {

                $models['a'] = $this->model;
                $models['b'] = $fields[$this->field]->getReferenceModel();

                $field_name = $this->field;

            }

            # Uso invertido

            else {

                $models['a'] = $fields[$this->field]->getReferenceModel();
                $models['b'] = $this->model;

                foreach ($models['a']->getManyToMany() as $key => $many_to_many) {
                    if ($many_to_many->class_name == get_class($this->model)) {
                        $field_name = $key;
                        break;
                    }
                }

                if (!isset($field_name)) {
                    throw new Exception("Referência original não encontrada.", log: false);
                }

                $this->left = 'b';
                $this->right = 'a';

            }

            $this->_table = $models['a']->getTable();

            $this->_table->table .= '_has_' . $field_name;

            foreach (['a', 'b'] as $key) {

                $this->_fields[$key] = new ForeignKeyField(get_class($models[$key]), real: true, type: 'CASCADE');
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
