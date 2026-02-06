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

            $fields = $this->model->getFields();

            # Uso normal

            if (!$this->inverted) {

                $model_a = $this->model;
                $model_b = $fields[$this->field]->getReferenceModel();

                $field_name = $this->field;

            }

            # Uso invertido

            else {

                $model_a = $fields[$this->field]->getReferenceModel();
                $model_b = $this->model;

                foreach ($model_a->getManyToMany() as $key => $many_to_many) {
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

            $this->_table = $model_a->getTable();

            $this->_table->table .= '_has_' . $field_name;

            $this->_fields['a'] = new Database\ForeignKeyField(get_class($model_a), real: true, type: 'CASCADE');
            $this->_fields['a']->column_name = 'a';
            $this->_fields['a']->required = true;
            $this->_fields['a']->primary_key = true;
            $this->_fields['a']->setName('a');
            $this->_fields['a']->setModel($this);
            $this->_fields['a']->initialize();

            $this->_fields['b'] = new Database\ForeignKeyField(get_class($model_b), real: true, type: 'CASCADE');
            $this->_fields['b']->column_name = 'b';
            $this->_fields['b']->required = true;
            $this->_fields['b']->primary_key = true;
            $this->_fields['b']->setName('b');
            $this->_fields['b']->setModel($this);
            $this->_fields['b']->initialize();

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
