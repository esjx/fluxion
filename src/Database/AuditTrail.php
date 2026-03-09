<?php
namespace Fluxion\Database;

use Attribute;
use ReflectionException;
use Fluxion\{AuditTrailModel, Config, FluxionException, Model, State, Time};

#[Attribute(Attribute::TARGET_CLASS)]
class AuditTrail
{

    protected Model $_model;
    protected AuditTrailModel $_audit_model;

    public function __construct(protected string $user_class,
                                protected string $cost_center_class,
                                protected ?string $title = null,
                                protected ?string $model_class = null)
    {

    }

    public function initialize(Model $model): void
    {
        $this->_model = $model;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @throws FluxionException
     */
    public function getAuditTrailModel(): AuditTrailModel
    {

        if (empty($this->_audit_model)) {

            $auth = Config::getAuth();

            if (!is_null($this->model_class)) {
                $class = new $this->model_class;
            }

            else {
                $class = $this->_model;
            }

            $this->_audit_model = new AuditTrailModel($class, $this->user_class, $this->cost_center_class);
            $this->_audit_model->_user = $auth->getUser()->login;
            $this->_audit_model->_cost_center = $auth->getUser()->cost_center;
            $this->_audit_model->_insert = Time::NOW->value();

        }

        return $this->_audit_model;

    }

    /**
     * @throws FluxionException
     */
    public function getFieldId(Model $model): ?Field
    {

        # Verifica se o log é gravado no Model "pai"

        $class_name = get_class($model);

        if (!is_null($this->model_class)
            && $this->model_class != $class_name) {


            foreach ($model->getForeignKeys() as $field) {

                if ($field->class_name == $this->model_class) {

                    return $field;

                }

            }

            throw new FluxionException("Não foi possível achar o relacionamento entre '$class_name' e '$this->model_class'");

        }

        return $model->getFieldId();

    }

    /**
     * @throws FluxionException
     * @throws ReflectionException
     */
    public function registerUpdate(Model $model): void
    {

        # Busca as alterações nos campos

        $changes = [];

        foreach ($model->getFields() as $key => $field) {

            $field->load();

            $detail = $model->getDetail($key);

            if ($field->needsAudit() && $field->isChanged()) {

                $label = $detail->label ?? $key;

                $old_value = $field->getSavedValue();
                $new_value = $field->getValue();

                if ($new_value == $old_value) {
                    continue;
                }

                $old_value = $field->getAuditValue($old_value);
                $new_value = $field->getAuditValue($new_value);

                $changes[] = "<small class='text-muted'>$label</small>: $old_value <small class='text-muted'>&rarr;</small> $new_value";

            }

        }

        # Registra a alteração se necessário

        if (count($changes) > 0) {

            $this->_model->changeState(State::AUDIT_TRAIL);

            $audit_model = $this->getAuditTrailModel();
            $audit_model->id = $this->getFieldId($model)->getValue();

            $audit_model->data = ($this->title) ? "<b class='text-black'>$this->title</b><br>" : '';
            $audit_model->data .= implode('<br>', $changes);

            $audit_model->save();

        }

    }

    /**
     * @throws FluxionException
     * @throws ReflectionException
     */
    public function registerDelete(Model $model): void
    {

        $this->_model->changeState(State::AUDIT_TRAIL);

        $audit_model = $this->getAuditTrailModel();
        $audit_model->id = $this->getFieldId($model)->getValue();

        $audit_model->data = ($this->title) ? "<b class='text-black'>$this->title</b><br>" : '';
        $audit_model->data .= '<i class="text-red">Registro Apagado</i>';

        $audit_model->save();

    }

}
