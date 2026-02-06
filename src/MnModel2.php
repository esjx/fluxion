<?php
namespace Fluxion;

use Fluxion\Query\Query2;

class MnModel2 extends Model2
{

    #[Database\PrimaryKey]
    public mixed $a;

    #[Database\PrimaryKey]
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

            # Uso normal

            $fields = $this->model->getFields();

            if (!$this->inverted) {

                $model_a = $this->model;
                $model_b = $fields[$this->field]->many_to_many->getReferenceModel();

                $field_name = $this->field;

            } # Uso invertido

            else {

                $model_a = $fields[$this->field]->many_to_many->getReferenceModel();
                $model_b = $this->model;

                foreach ($model_a->getManyToMany() as $key => $many_to_many) {

                    if ($many_to_many->class_name == get_class($this->model)) {

                        $field_name = $key;

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

            $this->_foreign_keys['a'] = new Database\ForeignKey(get_class($model_a), real: true, type: 'CASCADE');
            $this->_foreign_keys['a']->setName('a');

            $this->_foreign_keys['b'] = new Database\ForeignKey(get_class($model_b), real: true, type: 'CASCADE');
            $this->_foreign_keys['b']->setName('b');

            $this->_fields['a'] = clone $model_a->getFieldId();
            $this->_fields['a']->column_name = 'a';
            $this->_fields['a']->required = true;
            $this->_fields['a']->foreign_key = $this->_foreign_keys['a'];

            $this->_fields['b'] = clone $model_b->getFieldId();
            $this->_fields['b']->column_name = 'b';
            $this->_fields['b']->required = true;
            $this->_fields['b']->foreign_key = $this->_foreign_keys['b'];

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
