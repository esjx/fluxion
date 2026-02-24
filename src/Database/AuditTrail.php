<?php
namespace Fluxion\Database;

use Attribute;
use ReflectionException;
use Fluxion\{AuditTrailModel, Config, Exception, Model, Time};

#[Attribute(Attribute::TARGET_CLASS)]
class AuditTrail
{

    protected Model $_model;
    protected AuditTrailModel $_audit_model;

    public function __construct(protected string $user_class,
                                protected string $cost_center_class)
    {

    }

    public function initialize(Model $model): void
    {
        $this->_model = $model;
    }

    /**
     * @throws Exception
     */
    public function getAuditTrailModel(): AuditTrailModel
    {

        if (empty($this->_audit_model)) {

            $this->_audit_model = new AuditTrailModel($this->_model, $this->user_class, $this->cost_center_class);

        }

        return $this->_audit_model;

    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function register(Model $model): void
    {

        $audit_model = $this->getAuditTrailModel();
        $auth = Config::getAuth();

        $field_id = $model->getFieldId();

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

        if (count($changes) > 0) {

            $audit_model->_user = $auth->getUser()->login;
            $audit_model->_cost_center = $auth->getUser()->cost_center;
            $audit_model->_insert = Time::NOW->value();
            $audit_model->id = $field_id->getValue();
            $audit_model->data = implode('<br>', $changes);

            $audit_model->save();

        }

    }

}
