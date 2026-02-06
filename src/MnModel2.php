<?php
namespace Fluxion;

use Fluxion\Query\Query2;

class MnModel2 extends Model2
{

    public mixed $a;
    public mixed $b;

    protected string $left = 'a';
    protected string $right = 'b';

    public function getLeft(): string
    {
        return $this->left;
    }

    public function getRight(): string
    {
        return $this->right;
    }

    /** @throws CustomException */
    public function __construct(protected ?Model2 $model = null,
                                protected ?string $field = null,
                                protected bool $inverted = false)
    {

        if (!is_null($model)) {

            /** @var array<string, Model2> $models */
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
                    throw new CustomException("Referência original não encontrada.", log: false);
                }

                $this->left = 'b';
                $this->right = 'a';

            }

            $this->_table = $models['a']->getTable();

            $this->_table->table .= '_has_' . $field_name;

            foreach (['a', 'b'] as $name) {

                $this->_fields[$name] = new Database\ForeignKeyField(get_class($models[$name]), real: true, type: 'CASCADE');
                $this->_fields[$name]->column_name = $name;
                $this->_fields[$name]->required = true;
                $this->_fields[$name]->primary_key = true;
                $this->_fields[$name]->setName($name);
                $this->_fields[$name]->setModel($this);
                $this->_fields[$name]->initialize();

            }

            unset($this->a);
            unset($this->b);

        }

        parent::__construct();

    }

    /**
     * @throws CustomException
     */
    public function load($id): array
    {

        $query = new Query2($this);

        return $query->filter($this->left, $id)->only($this->right)->toArray();

    }

}
