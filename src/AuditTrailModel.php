<?php
namespace Fluxion;

use Fluxion\Auth\{UserModel, CostCenterModel};
use Fluxion\Database\Field\{AutoIncrementField, DateTimeField, ForeignKeyField, IntegerField, StringField, TextField};

class AuditTrailModel extends Model
{

    #[AutoIncrementField]
    public ?int $_id;

    #[DateTimeField(required: true, protected: true, default: 'getdate()')]
    public ?string $_insert;

    #[StringField]
    public ?string $_user;

    #[IntegerField]
    public ?int $_cost_center;

    #[StringField]
    public null|int|string $id;

    #[TextField]
    public ?string $data;

    /**
     * @throws Exception
     */
    public function __construct(protected Model  $model,
                                protected string $user_class,
                                protected string $cost_center_class)
    {

        # Dados originais do Model, de usuário e de centro de custo

        $id_model = $model->getFieldId();

        $user_model = new $this->user_class;

        if (!$user_model instanceof UserModel) {
            throw new Exception("Classe '$user_class' não estende UserModel");
        }

        $id_user = $user_model->getFieldId();

        $cost_center_model = new $this->cost_center_class;

        if (!$cost_center_model instanceof CostCenterModel) {
            throw new Exception("Classe '$cost_center_model' não estende CostCenterModel");
        }

        $id_cost_center = $cost_center_model->getFieldId();

        # Nome da tabela e detalhes

        $this->comment = get_class($model) . " [Audit Trail]";

        $this->_table = $this->model->getTable();

        $this->_table->table .= '__audit_trail';

        # Campo de usuário

        unset($this->_user);

        $this->_fields['_user'] = new ForeignKeyField($this->user_class, real: true);
        $this->_fields['_user']->column_name = '_user';
        $this->_fields['_user']->required = true;
        $this->_fields['_user']->setName('_user');
        $this->_fields['_user']->setModel($this);
        $this->_fields['_user']->setTypeProperty($id_user->getTypeProperty());
        $this->_fields['_user']->initialize();

        # Campo de centro de custo

        unset($this->_cost_center);

        $this->_fields['_cost_center'] = new ForeignKeyField($this->cost_center_class, real: true);
        $this->_fields['_cost_center']->column_name = '_cost_center';
        $this->_fields['_cost_center']->required = true;
        $this->_fields['_cost_center']->setName('_cost_center');
        $this->_fields['_cost_center']->setModel($this);
        $this->_fields['_cost_center']->setTypeProperty($id_cost_center->getTypeProperty());
        $this->_fields['_cost_center']->initialize();

        # Campo de identificação

        unset($this->id);

        $this->_fields['id'] = new ForeignKeyField(get_class($this->model), real: true);
        $this->_fields['id']->column_name = 'id';
        $this->_fields['id']->required = true;
        $this->_fields['id']->setName('id');
        $this->_fields['id']->setModel($this);
        $this->_fields['id']->setTypeProperty($id_model->getTypeProperty());
        $this->_fields['id']->initialize();

        parent::__construct();

    }

    public function _query(): Query
    {
        return new Query($this);
    }

}
