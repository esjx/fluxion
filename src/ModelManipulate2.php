<?php
namespace Fluxion;

use Fluxion\Auth\Auth;
use Fluxion\Auth\Models\Permission;

class ModelManipulate2 extends ModelManipulate
{

    public static function synchronize(string $model): void
    {

        /** @var Config $config */
        $config = $GLOBALS['CONFIG'];

        try {

            /** @var Model2 $class_model */
            $class_model = new $model;

            echo "<b style='color: orange;'>/* $model */</b>\n\n";

            //$dbId = $class_model->getDatabase();

            $connector = $config->getConnectorById(0);

            $obj = $connector->getPDO();

            // Criar as Permissões
            /*if ($model != 'Fluxion\Auth\Models\Permission' && $model != 'Fluxion\Auth\Models\PermissionGroup') {

                $perm = Permission::filter('name', $model)->firstOrNew($config, $auth);
                $perm->name = $model;
                $perm->save();

            }*/

            /*if ($class_model->_view) {

                if ($class_model->_view_script != '') {

                    $obj->beginTransaction();

                    if (is_array($class_model->_view_script)) {

                        foreach ($class_model->_view_script as $sql) {

                            $obj->exec($sql);

                        }

                    } else {

                        $obj->exec($class_model->_view_script);

                    }
                    $obj->commit();

                }

                return false;
            }*/

            $class_model->changeState(Model::STATE_SYNC);

            // Criar a tabela principal
            $connector->synchronize($class_model);

            echo "\n\n";

            return;

            // Criar as tabelas M:N
            foreach ($class_model->getFields() as $key=>$value) {

                if (isset($value['many_to_many'])) {

                    $class_model_MN = new $value['many_to_many'];

                    $fake = $value['many_to_many_fake'] ?? false;

                    if ($drop) {
                        $sql = $connector->drop(array(
                            'table' => $class_model->getTable() . '_has_' . $key,
                        ));
                        Application::trackerSql($sql);
                        $obj->exec($sql);
                    }

                    $sql = $connector->create(array(
                        'model' => get_class($class_model) . " MN[$key]",
                        'table' => $class_model->getTable() . '_has_' . $key,
                        'fields' => array(
                            'a' => array(
                                'type' => /*($class_model->_field_id == 'id')
                                    ? 'integer'
                                    : */ $class_model->_fields[$class_model->_field_id]['type'],
                                'maxlength' =>
                                    //($class_model->_field_id == 'id')
                                    //    ? 255
                                    //    : (
                                    (isset($class_model->_fields[$class_model->_field_id]['maxlength']))
                                        ? $class_model->_fields[$class_model->_field_id]['maxlength']
                                        : 255,
                                    //),
                                'required' => true,
                                'primary_key' => true,
                                'cascade' => true,
                                'foreign_key' => get_class($class_model),
                            ),
                            'b' => array(
                                'type' => /*($class_model_MN->_field_id == 'id')
                                    ? 'integer'
                                    : */ $class_model_MN->_fields[$class_model_MN->_field_id]['type'],
                                'maxlength' =>
                                    ($class_model_MN->_field_id == 'id')
                                        ? 255
                                        : (
                                    (isset($class_model_MN->_fields[$class_model_MN->_field_id]['maxlength']))
                                        ? $class_model_MN->_fields[$class_model_MN->_field_id]['maxlength']
                                        : 255
                                    ),
                                'required' => true,
                                'primary_key' => true,
                                'foreign_key' => ($fake) ? null : $value['many_to_many'],
                            ),
                        ),
                        'field_id' => '',
                        'field_id_ai' => false,
                        'indexes' => array(
                            array('a', 'b'),
                        ),
                    ), $class_model_MN);
                    Application::trackerSql($sql);
                    $obj->exec($sql);

                }

                if (isset($value['many_choices'])) {

                    if ($drop) {
                        $sql = $connector->drop(array(
                            'table' => $class_model->getTable() . '_has_' . $key,
                        ));
                        Application::trackerSql($sql);
                        $obj->exec($sql);
                    }

                    $sql = $connector->create(array(
                        'model' => get_class($class_model) . " MN[$key]",
                        'table' => $class_model->getTable() . '_has_' . $key,
                        'fields' => array(
                            'a' => array(
                                'type' => $class_model->_fields[$class_model->_field_id]['type'],
                                'maxlength' =>
                                    ($class_model->_field_id == 'id')
                                        ? 255
                                        : (
                                    (isset($class_model->_fields[$class_model->_field_id]['maxlength']))
                                        ? $class_model->_fields[$class_model->_field_id]['maxlength']
                                        : 255
                                    ),
                                'required' => true,
                                'primary_key' => true,
                                'foreign_key' => get_class($class_model),
                            ),
                            'b' => array(
                                'type' => $value['type'],
                                'maxlength' => ((isset($class_model->_fields[$key]['maxlength']))
                                    ? $class_model->_fields[$key]['maxlength']
                                    : 255),
                                'required' => true,
                                'primary_key' => true,
                            ),
                        ),
                        'field_id' => '',
                        'field_id_ai' => false,
                        'indexes' => array(
                            array('a', 'b'),
                        ),
                    ), $class_model);
                    Application::trackerSql($sql);
                    $obj->exec($sql);

                }

            }

        } catch (\PDOException $e) {

            if ($obj->inTransaction())
                $obj->rollBack();

            $connector->error($e);

        }

    }

}
